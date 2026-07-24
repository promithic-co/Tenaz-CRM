# Backoffice Super-Admin — Plano de Implementação

**Objetivo**: espaço separado e seguro para o super-admin gerenciar, por empresa (tenant), a
configuração de IA de cada agente — prompt, ferramentas e modelo LLM — com troca fácil de empresa
e em escala. Inspirado no backoffice do Estalo (`D:\DOCS\PROMOSYS\Estalo_hub\estalo`), adaptado à
arquitetura multi-agente do Aria (nunca cópia 1:1).

Status: **plano completo — slices 1 (fundação), 2 (modelo LLM), 3 (ferramentas) e 4 (prompt)
implementados.**

Slice 4 entregue: aba `agentes/{agent}/prompt` no cockpit, escrevendo um `PromptTemplate`
`type=system` por agente (`tenant_id` + `agent_id`), versionado via `saveNewVersion()`. Dois modos:
**estruturado** (lista ordenada de seções, semeada com o miolo que o runtime compõe hoje — core
FORMATO + `niche_sections` do template) e **cru** (texto livre). Colunas novas:
`prompt_templates.sections` (json, estado do editor) + `editor_mode`.

Contrato de segurança: o editor só controla o **miolo**. `AgentPromptComposer` sempre prende a
abertura (`PromptComposer::preamble`) e a cauda (`coreClosingSections`: FERRAMENTAS, SEGURANÇA,
ENCERRAMENTO) em torno do que o operador escreveu — inclusive no modo cru, que era o furo apontado
no §7. Head e tail saem do próprio `PromptComposer` (fonte única, sem segunda cópia pra divergir);
por isso 4 métodos dele viraram `public` (mudança de visibilidade, zero mudança de comportamento).

Divergências do slice 4 em relação ao plano:
- **Placeholders normalizados no save.** `PromptTemplate::render()` não remove `{{...}}` órfão e o
  mapa de variáveis do agente não tem `personality_block` nem `no_reply_sentinel`. O compositor
  troca o primeiro por `{{agent_personality}}` e escreve o sentinela literal.
- **A cauda respeita as capabilities do slice 3** no momento do save — prompt salvo nunca manda
  acionar tool desligada. Como o texto congela, mudar ferramentas depois pede re-save (a tela avisa).
- **Botão "voltar ao prompt padrão"**: desativa o override (`is_active = false`, histórico
  preservado) e o agente volta a compor pelo `PromptComposer`. Rollback pra versão específica ficou
  de fora — o histórico é exibido, mas só leitura.
- Templates da empresa inteira (`agent_id` null, ex. os do `credflow:publish-prompt`) são
  deliberadamente ignorados na edição: salvar aqui nunca reescreve prompt compartilhado.

Slice 2 entregue: cockpit `agentes` no backoffice (lista da empresa ativa + tela por agente),
edição de `agent_provider` / `agent_model` / `temperature` em `AgentConfig`, exibindo o valor
**efetivo** (após a cascata config → template → hard default). Cache `agent_config_id_{id}` é
bustado pelo hook `saved` do model (não precisou de `Cache::forget` no controller). A linha de
`AgentConfig` é criada sob demanda quando o agente ainda não tem uma, com o mesmo seed que o
`AgentConfigController` já usa. Isolamento vem do route-model binding: agente de outra empresa
dá 404 enquanto há empresa ativa.

Slice 3 entregue: enum `AgentToolCapability` (7 tools nativas, valor = nome que a LLM vê) +
coluna `agent_configs.tool_capabilities` (json, nullable). **null = sem restrição** (agente mantém
o toolset inteiro), `[]` = nenhuma tool nativa. O filtro vive em
`BaseCustomerServiceAgent::applyToolCapabilities()` e é aplicado também nos 4 overrides de
`tools()` (Generic, CredFlowBulk, CredFlowFollowUp, GenericFollowUp) — desligar uma capability
vale no follow-up também, não só no turno principal. Tool sem capability mapeada (webhook, tool
nova) **sempre passa**; webhook continua governado por `ToolDefinition.is_active`.
Tela: aba `agentes/{agent}/ferramentas` no cockpit.

Divergências do slice 3 em relação ao plano:
- **O prompt também honra as capabilities.** As seções core do `PromptComposer` mandavam acionar
  `escalar_para_humano` (SEGURANÇA) e `atualizar_status_lead` (ENCERRAMENTO) mesmo com a tool
  desligada — prompt pedindo tool fora da allowlist. `coreClosingSections()` agora lê
  `tool_capabilities` das variáveis e troca/remove essas instruções. Sem seleção salva o texto
  sai byte-a-byte igual (golden file intacto).
- **Limitação conhecida**: os agentes legados de heredoc (`CredFlowAgent`, `SiapeAgent`,
  `CltAgent`) citam `consultar_credito_*` direto no texto do prompt. A tool some do toolset, mas
  o texto continua mencionando. Só o caminho `PromptComposer` (agentes criados por template) está
  coberto.
- **Pendência pro slice 4**: quando `AgentPromptComposer` re-anexar as seções de segurança no modo
  cru, passar `tool_capabilities` para `coreClosingSections()` — senão o prompt cru volta a pedir
  tool desligada.

Ajustes feitos durante a execução do slice 1 (divergências do plano original):
- Não há redirect forçado pra tela de seleção. O backoffice tem **zona global** (empresas,
  templates LLM, modelos de agente — sem empresa ativa) e a seleção é opcional; sem seleção o
  super-admin mantém a visão cross-tenant histórica.
- `ActiveTenant::id()` lê a sessão pelo facade `Session` (não `request()->session()`), porque o
  global scope roda fora da camada de controller.
- `BackofficeTenantController` conta agentes com `withoutGlobalScope('tenant')` pra lista de
  empresas não zerar enquanto se atua como uma delas.
- Frontend não usa Wayfinder no backoffice (assa o prefixo no bundle): `useBackofficeRoutes`
  monta as URLs a partir do prop `backoffice.path`. O Wayfinder **continua gerando**
  `resources/js/routes/backoffice/**` e as actions de `Backoffice*Controller` com o prefixo do
  build assado dentro; enquanto ninguém importa esses arquivos, nada vaza pro bundle.
  `BackofficePathLeakTest` falha se algum arquivo de `resources/js` importar um deles.

---

## 1. Contexto — o "stack de prompt" do Aria

Cada turno do agente monta o prompt em camadas, todas já multi-tenant no banco mas espalhadas:

| Camada | Onde vive | Escopo | Editável hoje |
|---|---|---|---|
| Classe do agente | `AgentFactory` → NicheTemplate.agent_class / mapa niche / GenericAgent | niche | código/registry |
| Config LLM | `AgentConfig` (empresa) → `AgentTemplateConfig` (template) → hard defaults, waterfall em `AgentConfigResolver` | empresa+template | só template global no backoffice |
| Texto do prompt | `PromptTemplate.content` (DB, tenant+agent) → senão heredoc PHP / `PromptComposer` (core + `NicheTemplate.niche_sections`) | empresa | só via `credflow:publish-prompt` (CLI) |
| Experimentos A/B | `PromptExperiment` | empresa | nenhuma UI |
| Ferramentas | base em código (gated por status) + `ToolDefinition` webhooks (DB, `is_active`) | empresa+agente | nenhuma UI |
| Regras operacionais | `AgentOperationalRule` → variável no prompt | empresa | tela solta |
| Observabilidade | `AiRun` (arch, prompt_hash, outcome, custo) | empresa | Laboratory (read-only) |

**Terminologia da tela `laboratory/ai-usage`:**
- **Arquitetura** (`architecture_version`): marcador de experimento, global via env
  `TENAZ_AGENT_ARCHITECTURE_VERSION`. Valores: `legacy_prompt` (default), `folder_skills`, `hybrid`.
- **legacy_prompt**: prompt montado do jeito clássico (classes PHP + PromptTemplate). Não é entidade
  separada, é um dos 3 valores de arquitetura.
- **Prompt/Skill** (coluna, ex. `ca978112`): `prompt_hash` truncado — hash do system prompt daquele
  turno. `-` quando não houve chamada LLM.
- **Outcome**: resultado de negócio derivado do status do lead (`qualified`, `scheduled`,
  `transferred`, `asked_next_question`, `replied`, `no_response`). Diferente de **Status** (técnico:
  `success`/`error`/`fallback`/`human_handoff`).

## 2. Backoffice que já existe

`/backoffice` guardado por `EnsureSuperAdmin` (`user.is_super_admin`; **limpa `active_tenant_id`** →
roda sem escopo). 3 telas: `templates` (AgentTemplateConfig LLM global por slug), `modelos` (toggle
registry NicheTemplate), `tenants` (lista read-only). **Falta**: editar prompt por empresa, toggle de
tools, drill-in por empresa, versionamento/rollback, cockpit unificado.

## 3. Padrão do Estalo (referência)

- **URL aleatória fixa**: `config/admin.php` → `env('ADMIN_PATH','admin')`; `routes/admin.php` usa
  `->prefix(config('admin.path'))`. Obscuridade EM CIMA do gate super_admin.
- **Troca de empresa SEM tenant no URL**: rotas agem na promotora ATIVA. `ActivePromotora` (sessão
  `active_promotora_id`, `selectForSuperAdmin`, `availableForSuperAdmin`) + `ResolveTenantContext`
  middleware → `TenantContext`. Controllers: `updateOrCreate(['promotora_id'=>$tenant->id()], ...)`
  com `withoutGlobalScopes()`.
- **Config num único model** `PromotoraConfiguration` (prompt+model+tools numa linha).
- **Prompt**: cru + `{{placeholders}}`; `AgentPromptBuilder` monta `{{personality_block}}` de campos
  estruturados (tone/formality/verbosity/emoji/traits enums).
- **Tools**: enum `AtendimentoToolCapability` (label/description/defaults/options), config guarda
  habilitadas como json array.
- **Frontend**: `AdminLayout` separado (zinc escuro), `useAdminRoutes`, páginas `Admin/*`.

## 4. Comparação crítica — o que adotar

| Aspecto | Estalo | Aria hoje | Decisão |
|---|---|---|---|
| URL random | `config('admin.path')` | prefixo hardcoded | **Adotar**: `config/backoffice.php` + env |
| Troca empresa | sessão, sem URL | tem `active_tenant_id` mas EnsureSuperAdmin apaga | **Adotar modelo de sessão** (URL não explícito) |
| Config | 1 linha/promotora | espalhado em 5 models, runtime depende | **NÃO copiar** — manter models, unificar só na TELA |
| Granularidade | por promotora | por **agente** (empresa tem N agentes) | Empresa ativa → escolhe agente → edita |
| Prompt | cru + personalidade estruturada | PromptComposer + PromptTemplate | Estruturado + cru |
| Tools | enum capabilities | base hardcoded + ToolDefinition webhooks | Enum capabilities + toggle webhook |
| Layout | AdminLayout separado | reusa AppLayout | **Adotar** layout dedicado |

## 5. Decisões travadas

- **Troca de empresa**: seletor por sessão (não URL explícito).
- **Prompt**: editor estruturado (seções) + override texto cru, versionado.
- **v1**: prompt + tools + modelo LLM (incl. model slug por agente do tenant).
- **Tools**: webhooks + **capabilities built-in** (Estalo-style) → muda runtime, agentes honram set
  de capacidades por agente.
- **Isolamento**: **modelo Estalo global** — reusa `active_tenant_id`; super-admin escolhe empresa e
  usa o app inteiro como ela.

## 6. Arquitetura final

### 6.1 URL aleatória fixa
- Novo `config/backoffice.php`: `'path' => env('BACKOFFICE_PATH', 'backoffice')`.
- `routes/backoffice.php`: prefixo → `config('backoffice.path')`.
- Prod: `BACKOFFICE_PATH=<string-longa-random>`. Obscuridade + gate `super_admin` (já existe).

### 6.2 Empresa ativa global (modelo Estalo) — mudança de core ⚠️
Maior risco do plano. Mexe no escopo multi-tenant central.
- **`EnsureSuperAdmin`** (`app/Http/Middleware/EnsureSuperAdmin.php`): parar de apagar
  `active_tenant_id`. Sem seleção → redireciona pra tela de escolha de empresa.
- **`BelongsToTenant`** (`app/Models/Concerns/BelongsToTenant.php`): hoje super-admin ignora escopo
  (vê tudo). Passa a: super-admin **com** `active_tenant_id` → escopa àquela empresa (age como ela no
  app todo). Sem seleção → unscoped.
- Switcher de empresa (dropdown listando tenants) no topo do backoffice + endpoint de troca. Lista de
  todas as empresas usa `withoutGlobalScopes()` (padrão Estalo).
- **`User::getTenantIdAttribute()`** (`app/Models/User.php:69`) já lê `active_tenant_id` — reaproveitado.

### 6.3 Layout separado
`BackofficeLayout.vue` dedicado (não reusa AppLayout), switcher fixo no topo. Páginas em
`resources/js/pages/backoffice/`.

### 6.4 Cockpit por agente
Empresa ativa → cockpit lista **agentes** dela → por-agente, abas **Prompt | Ferramentas | Modelo**.

## 7. Mudanças de runtime

1. **Escopo tenant**: `BelongsToTenant` honra `active_tenant_id` para super-admin (§6.2).
2. **Tool capabilities**: novo enum `AgentToolCapability` espelhando as tools nativas do Aria
   (`consultar_credito_inss`/`_siape`/`_clt`, `escalar_para_humano`, `registrar_informacao_contato`,
   `registrar_lead_sem_credito`, `atualizar_status_lead`). Persistido por agente.
   `BaseCustomerServiceAgent::tools()` (`app/Ai/Agents/BaseCustomerServiceAgent.php:201`) passa a
   filtrar pelo set habilitado (além do gate por status atual). `ToolDefinition` webhook segue via
   `is_active`.
3. **Prompt estruturado**: coluna `sections` (json) + `editor_mode` em `prompt_templates`. Runtime
   segue lendo só `content` (composto no save) — **zero mudança de runtime aqui**.

### Risco de segurança tratado — override cru pula seções de segurança
`GenericAgent` (`app/Ai/Agents/GenericAgent.php:33`): se existe `PromptTemplate` no DB, usa
`template->render()` e **não** chama `PromptComposer`, que injeta as seções blindadas (FERRAMENTAS,
SEGURANÇA, ENCERRAMENTO, personality firewall). Logo, prompt cru salvo remove proteções. **No modo
cru, o backoffice re-anexa as seções core de segurança** (ou avisa forte na UI). Modo estruturado já
passa pelo composer.

### Cache
`AgentConfigResolver` (`app/Services/AgentConfigResolver.php:40`) cacheia `agent_config_id_{id}` por
300s sem observer. `PromptTemplate`/`ToolDefinition` já bustam via `PromptLayerCache`. **Editar
AgentConfig precisa `Cache::forget` no save.**

## 8. Migrações

- `agent_configs`: add `tool_capabilities` (json, nullable).
- `prompt_templates`: add `sections` (json, nullable) + `editor_mode` (string, nullable).

## 9. Slices (commits atômicos, cada um testado + mergeável)

1. **Fundação** — `config/backoffice.php` (URL random) + `BackofficeLayout` + switcher de empresa +
   **mudança de escopo tenant** (§6.2) + tela de seleção. *(maior risco — testar pesado.)*
2. **Modelo LLM** por agente — edita `AgentConfig` (provider + model slug + temperatura) da empresa
   ativa + `Cache::forget`.
3. ~~**Ferramentas** — enum `AgentToolCapability` + runtime honra (`tools()`) + toggle webhook
   `ToolDefinition`.~~ ✅ (+ seções core do prompt honram as capabilities)
4. **Prompt** — editor estruturado (seções PromptComposer) + override cru (`PromptTemplate`,
   versionado via `saveNewVersion`) + re-anexa seções de segurança no modo cru.

## 10. Testes (Pest, por slice)

- 403 para não-super-admin (padrão `BackofficeAccessTest`).
- URL random resolve pelo `config('backoffice.path')`.
- Switch troca empresa ativa; app principal escopa à empresa selecionada.
- **Isolamento cross-tenant**: super-admin editando empresa A nunca toca dados de B; com/sem seleção.
- Cada editor persiste + busta cache escopado à empresa ativa.
- Agente respeita capabilities desligadas (tool não aparece no toolset).
- Prompt cru mantém as seções de segurança.

## 11. Convenções do projeto (obrigatório)

- `php artisan make:` para novos arquivos; `--no-interaction`.
- FormRequests para validação (reusar whitelist de provider de `StoreAgentTemplateConfigRequest`).
- `vendor/bin/pint --dirty --format agent` antes de finalizar.
- Wayfinder: importar rotas de `@/actions` / `@/routes` no frontend.
- Toda mudança testada (`php artisan test --compact --filter=...`).
- Após push: merge em main + push origin main (deploy.sh puxa de main).

## Referências de código

Aria: `app/Services/AgentService.php`, `app/Services/AgentConfigResolver.php`,
`app/Services/PromptComposer.php`, `app/Ai/AgentFactory.php`,
`app/Ai/Agents/BaseCustomerServiceAgent.php`, `app/Ai/Agents/GenericAgent.php`,
`app/Models/{PromptTemplate,ToolDefinition,AgentConfig}.php`, `routes/backoffice.php`,
`app/Http/Middleware/EnsureSuperAdmin.php`, `app/Models/Concerns/BelongsToTenant.php`.

Estalo: `routes/admin.php`, `config/admin.php`, `app/Services/ActivePromotora.php`,
`app/Support/TenantContext.php`, `app/Http/Middleware/ResolveTenantContext.php`,
`app/Models/PromotoraConfiguration.php`, `app/Services/AgentPromptBuilder.php`,
`app/Enums/Agent/AtendimentoToolCapability.php`, `resources/js/pages/Admin/*`.
