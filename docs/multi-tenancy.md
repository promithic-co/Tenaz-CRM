# Multi-Tenancy no Aria

## Visão Geral

O Aria usa multi-tenancy baseado em **Tenant model** com relação many-to-many via pivot `tenant_user`. Cada usuário pertence a um ou mais tenants, e cada tenant agrupa dados isolados (leads, agentes, campanhas, etc.).

## Modelos Principais

### Tenant (`app/Models/Tenant.php`)

Modelo simples com `name`. Criado automaticamente no registro do usuário.

### User ↔ Tenant (pivot `tenant_user`)

```
users ←→ tenant_user ←→ tenants
           └── role (owner | administrator | user)
```

- `User::tenants()` — relação belongsToMany
- `User::tenantId` — accessor que retorna `(string) $firstTenant->id` (verifica session `active_tenant_id` primeiro)
- `User::currentRole()` — retorna o `TenantRole` enum do tenant ativo
- `User::isOwnerOrAdmin()` — verifica se o role é Owner ou Administrator

### Registro de Usuário (`CreateNewUser`)

Ao registrar, o sistema:
1. Cria o `User`
2. Cria um `Tenant` com o nome do usuário
3. Attach com role `Owner`

O `UserFactory` replica esse comportamento via `afterCreating`.

## BelongsToTenant Trait

**Arquivo:** `app/Models/Concerns/BelongsToTenant.php`

Trait aplicado em todos os models com dados tenant-scoped. Registra um **global scope** chamado `'tenant'` que filtra automaticamente:

```php
$query->where('tenant_id', auth()->user()?->tenantId);
```

**Efeito:** Toda query `SELECT` já vem filtrada pelo tenant do usuário autenticado. Modelos de outro tenant simplesmente não existem para o usuário.

### Models que usam BelongsToTenant

- Agent, AgentConfig, AgentOperationalRule
- Lead, FollowupMessage
- WhatsappInstance, WhatsappTemplate
- Campaign, CampaignMessage, ContactList
- VoiceInstance, VoiceCampaign
- FailedInteraction, ServiceTicket
- AiUsageDaily, PromptTemplate, ToolDefinition, CustomField, StatusMachine

## Coluna `tenant_id`

### Tipos de coluna (inconsistência conhecida)

Há dois padrões no schema:

| Tipo | Tabelas | Valor |
|------|---------|-------|
| `string('tenant_id')` | leads, agents, whatsapp_instances, service_tickets, followup_messages, agent_configs, agent_operational_rules, contact_lists, whatsapp_templates, etc. | `(string) $tenant->id` |
| `foreignId('tenant_id')` | campaigns, voice_campaigns, voice_instances | `(int) $tenant->id` |

O accessor `User::tenantId` retorna **string**. Comparações em policies fazem cast explícito: `(string) $model->tenant_id === (string) $user->tenantId`.

## Fluxo de Autenticação → Tenant

1. Usuário faz login
2. `auth()->user()` retorna o User
3. `$user->tenantId` busca o tenant ativo:
   - Primeiro tenta `session('active_tenant_id')` (para multi-tenant switching futuro)
   - Fallback: `$this->tenants()->first()->id`
4. Global scope `BelongsToTenant` usa esse valor em todas as queries

## Onde usar `tenantId`

### Correto
```php
$tenantId = auth()->user()->tenantId;        // accessor → string
$tenantId = $user->tenantId;                 // accessor → string
$tenantId = $agent->tenant_id;               // coluna do model
```

### Incorreto (bugs que foram corrigidos)
```php
$tenantId = auth()->id();                    // ← retorna USER id, não tenant id
$tenantId = (string) $user->id;              // ← user id, não tenant id
$tenantId = auth()->user()->tenant_id;       // ← coluna não existe no User model
```

## Bypass do Global Scope

Em contextos sem autenticação (jobs, webhooks, commands), o scope precisa ser removido:

```php
Model::withoutGlobalScope('tenant')->where(...);
```

Exemplos onde é necessário:
- `AgentOperationalRule::forUser()` — chamado por jobs de follow-up
- Queue workers processando leads
- Webhook handlers do WhatsApp
- Commands artisan

## Policies

Controllers que usam `$this->authorize()` verificam ownership via policies. Com o BelongsToTenant scope ativo, route model binding retorna **404** (não 403) para recursos de outro tenant — o model simplesmente não é encontrado.

```php
// CampaignPolicy
private function authorizeFor(User $user, Campaign $campaign): bool
{
    return (string) $campaign->tenant_id === (string) $user->tenantId
        && $user->isOwnerOrAdmin();
}
```

## Roles e Permissões

| Role | Permissões |
|------|-----------|
| **Owner** | Acesso total, não pode ser rebaixado |
| **Administrator** | Acesso total exceto gerenciar owner |
| **User** | Acesso restrito — só vê seus próprios agentes e leads |

A verificação de role restrito (`isRestrictedUser()`) é usada em policies para filtrar por `user_id` dentro do mesmo tenant.

## Factories (Testes)

O `UserFactory` auto-cria um Tenant com role Owner no `afterCreating`. Isso significa:

1. `User::factory()->create()` já vem com tenant
2. `$user->tenantId` funciona imediatamente
3. Não precisa criar tenant manualmente nos testes

Para testes que precisam de um usuário **sem** tenant:
```php
$user = User::factory()->create();
$user->tenants()->detach();
```

Para testes com tenant explícito (ex: RoleEnforcementTest):
```php
$user = User::factory()->create();
$user->tenants()->detach();  // remove auto-created
$user->tenants()->attach($specificTenant->id, ['role' => 'administrator']);
```

## Realinhamento de Dados Legados

Antes do multi-tenancy, `tenant_id` era `(string) $user->id`. O `LegacyTenantKeyRealignmentService` reescreve valores antigos:

```php
// Para cada usuário com tenant:
$legacyKey = (string) $user->id;
$canonicalKey = (string) $primaryTenant->id;
// UPDATE all tables SET tenant_id = $canonicalKey WHERE tenant_id = $legacyKey
```

Comando: `php artisan tenants:realign-legacy-keys`

## Checklist para Novos Models

1. Adicionar `use BelongsToTenant;` ao model
2. Incluir `tenant_id` no `$fillable`
3. Usar `string('tenant_id')->index()` na migration
4. Setar `tenant_id` no `create()` usando `auth()->user()->tenantId`
5. Em factories, usar `$user->tenantId` (não `$user->id`)
6. Em `firstOrCreate`/`updateOrCreate`, incluir `tenant_id` nos atributos de criação
7. Se o model é acessado fora de contexto auth, usar `withoutGlobalScope('tenant')`
