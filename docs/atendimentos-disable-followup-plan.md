# Plano: desligar follow-up por lead em `/atendimentos`

Data: 2026-05-10

## Objetivo

Adicionar em `/atendimentos` uma acao operacional para desligar o follow-up automatico de um lead sem precisar abrir `/conversas/{lead}`.

O comportamento alvo e simples:

- se o lead estiver com `followup_status = active` ou `paused`, o operador consegue desligar;
- desligar muda `leads.followup_status` para `inactive`;
- o scheduler `credflow:check-followups` e o job `ProcessLeadFollowUpJob` deixam de enviar novas mensagens porque ambos ja dependem de `followup_status = active`;
- a tela atualiza o badge/acao depois do POST;
- a acao respeita tenant e autorizacao do lead/ticket.

## Diagnostico do estado atual

### Tela `/atendimentos`

Arquivos principais:

- `routes/web.php`
- `app/Http/Controllers/ServiceTicketController.php`
- `resources/js/pages/atendimentos/Index.vue`
- `tests/Feature/ServiceTicketControllerTest.php`

Hoje a tela lista `ServiceTicket` com:

- filtros por status, tipo, motivo/resumo e data;
- acoes `claim`, `resolve`, `close`;
- link para `/conversas/{lead}`;
- acao de limpar historico da conversa.

O controller carrega `ServiceTicket::with(['lead', 'assignedUser'])`, mas o payload enviado ao Vue nao inclui `lead.followup_status` nem `lead.followup_count`.

### Follow-up existente

Arquivos principais:

- `app/Models/Lead.php`
- `app/Http/Controllers/LeadFollowUpController.php`
- `app/Console/Commands/CheckFollowUpsCommand.php`
- `app/Jobs/ProcessLeadFollowUpJob.php`
- `resources/js/pages/conversas/Show.vue`
- `tests/Feature/LeadFollowUpControllerTest.php`

Estados usados hoje em `leads.followup_status`:

- `active`: scheduler pode avaliar e disparar follow-up;
- `paused`: scheduler/job pulam e podem manter o lead retomavel;
- `inactive`: follow-up desligado/inativo.

A tela de conversa ja permite pausar/retomar:

- `POST /conversas/{lead}/followup-pause`: muda `active` para `paused`;
- `POST /conversas/{lead}/followup-resume`: muda `paused` para `active` se ainda estiver dentro da janela de atendimento.

Isso nao atende bem ao pedido de `/atendimentos` por tres motivos:

- `/atendimentos` nao recebe o estado de follow-up para renderizar a acao;
- "desligar" deve ser uma decisao explicita para `inactive`, nao apenas uma pausa retomavel;
- `LeadFollowUpController` retorna `success`, enquanto `ServiceTicketController::index` espera `flash`.

## Decisao de implementacao

Criar uma acao especifica de atendimento para desligar follow-up a partir do ticket:

`POST /atendimentos/{ticket}/followup-disable`

Motivos:

- usa o contexto natural da tela: o operador esta olhando um ticket;
- autoriza via `ServiceTicketPolicy::update` e, opcionalmente, valida o lead associado;
- evita depender de uma rota de conversa para uma acao operacional de atendimento;
- permite retornar `flash` consistente com a tela atual;
- reduz risco de o usuario desligar follow-up de um lead que nao pertence ao ticket exibido.

Nao criar migration nesta primeira entrega. O campo `followup_status` ja existe e `inactive` ja e respeitado pelo motor.

## Compatibilidade com leads existentes na VPS

A mudanca precisa funcionar com leads que ja estao em producao, sem exigir backfill obrigatorio nem alterar schema.

Constatacao do schema atual:

- `leads.followup_status` ja existe desde a migration inicial de leads;
- o tipo e `string(20)`, nao enum, portanto aceitar `inactive` nao depende de migration nova;
- o default ja e `inactive`;
- existem indices em `followup_status` e em `(tenant_id, followup_status)`, entao a atualizacao pontual por lead nao degrada o scheduler;
- `followup_count` ja existe e deve permanecer como historico.

Regra para VPS:

- leads existentes com `active` devem poder ser desligados para `inactive`;
- leads existentes com `paused` tambem devem poder ser desligados para `inactive`;
- leads existentes ja `inactive` devem continuar aceitando a acao de forma idempotente;
- qualquer valor inesperado ou nulo deve ser tratado como nao ativo na UI, mas o endpoint pode normalizar para `inactive` quando o operador clicar em desligar;
- nenhum lead existente deve ter `followup_count`, `last_interaction_at`, `last_inbound_at`, `status`, `conversation_id` ou `credito_json` alterado por essa acao.

Consulta pre-deploy recomendada na VPS:

```sql
select followup_status, count(*) as total
from leads
group by followup_status
order by total desc;
```

Se aparecerem valores fora de `active`, `paused`, `inactive` ou `null`, nao bloquear o deploy. Registrar os valores e tratar a acao de desligar como normalizacao para `inactive` apenas no lead clicado.

Consulta pos-deploy para validar que a acao nao quebrou leads existentes:

```sql
select id, followup_status, followup_count, updated_at
from leads
where id = :lead_id;
```

O resultado esperado apos desligar e somente `followup_status = inactive`, preservando `followup_count`.

## Backend

### 1. Expor dados de follow-up no payload da tela

Em `ServiceTicketController::index`, adicionar ao array `through()`:

- `lead_followup_status` => `$t->lead?->followup_status ?: 'inactive'`
- `lead_followup_count` => `(int) ($t->lead?->followup_count ?? 0)`
- opcional: `lead_status` => `$t->lead?->status`

Esses campos sao suficientes para a UI mostrar badge e decidir se exibe a acao.

### 2. Adicionar rota

Em `routes/web.php`, dentro do bloco de atendimentos:

```php
Route::post('/atendimentos/{ticket}/followup-disable', [ServiceTicketController::class, 'disableFollowUp'])
    ->name('atendimentos.followup.disable');
```

### 3. Implementar metodo no controller

Em `ServiceTicketController`:

- autorizar `update` no ticket;
- carregar `lead`;
- se nao houver lead, retornar `back()->with('flash', 'Lead nao encontrado para este atendimento.')`;
- se `followup_status !== 'inactive'`, atualizar para `inactive`;
- manter `followup_count` como historico, sem zerar;
- nao alterar nenhuma coluna comercial ou de conversa do lead;
- retornar `back()->with('flash', 'Follow-up desligado para este lead.')`;
- se ja estiver inativo, retornar mensagem idempotente: `Follow-up ja estava desligado para este lead.`

Pseudocodigo:

```php
public function disableFollowUp(ServiceTicket $ticket): RedirectResponse
{
    $this->authorize('update', $ticket);

    $ticket->loadMissing('lead');
    $lead = $ticket->lead;

    if (! $lead) {
        return back()->with('flash', 'Lead nao encontrado para este atendimento.');
    }

    if ($lead->followup_status !== 'inactive') {
        $lead->update(['followup_status' => 'inactive']);

        return back()->with('flash', 'Follow-up desligado para este lead.');
    }

    return back()->with('flash', 'Follow-up ja estava desligado para este lead.');
}
```

### 4. Opcional: centralizar no model

Hoje `Lead` tem `activateFollowUp()`, `pauseFollowUp()` e `resumeFollowUp()`.

Adicionar `disableFollowUp()` melhora consistencia:

```php
public function disableFollowUp(): void
{
    $this->update(['followup_status' => 'inactive']);
}
```

Usar esse metodo tanto no novo endpoint quanto, futuramente, em regras de handoff humano.

## Frontend

### 1. Atualizar tipo `Ticket`

Em `resources/js/pages/atendimentos/Index.vue`, adicionar:

- `lead_followup_status: 'active' | 'paused' | 'inactive' | string`
- `lead_followup_count: number`
- opcional: `lead_status: string`

### 2. Exibir badge na coluna de status ou lead

Sugestao visual compacta:

- `Follow-up ativo` em amarelo quando `active`;
- `Follow-up pausado` em cinza/amarelo quando `paused`;
- nao mostrar badge para `inactive`, ou mostrar `Follow-up desligado` apenas no modal/detalhes.

Como a tabela ja esta densa, a melhor posicao e abaixo dos badges de status/prioridade, na coluna `Status`.

### 3. Adicionar acao de desligar

Na area de acoes da linha, exibir botao quando:

- `ticket.lead_followup_status === 'active' || ticket.lead_followup_status === 'paused'`.

Texto recomendado:

- `Desligar follow-up`

Comportamento:

- abrir confirmacao simples antes do POST;
- usar `router.post(`/atendimentos/${ticket.id}/followup-disable`, {}, { preserveScroll: true })`;
- reutilizar `ticketActionLoading` ou criar `followupActionLoading` para evitar travar botoes de ticket sem necessidade.

Mensagem da confirmacao:

> Desligar o follow-up automatico deste lead? O historico sera mantido, mas novas tentativas automaticas nao serao enviadas.

### 4. Nao implementar retomada nesta entrega

Retomar follow-up a partir de `/atendimentos` parece tentador, mas tem regras de janela de 24h, status comercial e configuracao do agente.

Para manter a entrega segura:

- `/atendimentos` permite desligar;
- `/conversas/{lead}` continua sendo o local para controle mais detalhado de pausa/retomada;
- retomada em `/atendimentos` fica para uma segunda fase, se houver necessidade operacional.

## Testes

### Backend

Adicionar em `tests/Feature/ServiceTicketControllerTest.php`:

1. `operator can disable follow-up from atendimento ticket`
   - cria usuario, agent, lead com `followup_status = active`, ticket aberto;
   - POST `route('atendimentos.followup.disable', $ticket)`;
   - espera redirect;
   - espera `lead->fresh()->followup_status === 'inactive'`;
   - espera session `flash`.

2. `disable follow-up from atendimento is idempotent`
   - lead ja esta `inactive`;
   - POST nao altera contagem nem falha;
   - retorna redirect e flash.

3. `atendimentos index exposes lead follow-up state`
   - cria ticket com lead `active`, `followup_count = 2`;
   - GET `/atendimentos`;
   - assert Inertia contem `tickets.data.0.lead_followup_status = active` e `lead_followup_count = 2`.

4. `unauthorized user cannot disable follow-up through atendimento`
   - ticket de outro tenant;
   - POST deve retornar 404/403 conforme comportamento atual das policies/scopes.

5. `disable follow-up accepts existing production-like statuses`
   - usar data provider ou testes separados para `active`, `paused`, `inactive` e `null`;
   - POST deve concluir sem erro;
   - resultado deve ser `inactive`;
   - `followup_count` deve continuar igual.

6. `disable follow-up does not mutate lead business fields`
   - criar lead com `status`, `conversation_id`, `credito_json`, `last_interaction_at` e `last_inbound_at`;
   - desligar follow-up;
   - confirmar que somente `followup_status` mudou.

### Frontend

Se houver cobertura Vue/Playwright:

- renderiza badge `Follow-up ativo` quando prop vem `active`;
- botao `Desligar follow-up` aparece para `active` e `paused`;
- botao nao aparece para `inactive`;
- clique confirma e dispara POST para `/atendimentos/{id}/followup-disable`.

## Validacao manual

1. Criar ou selecionar lead qualificado com `followup_status = active`.
2. Garantir que existe ticket em `/atendimentos`.
3. Abrir `/atendimentos`.
4. Confirmar badge de follow-up ativo.
5. Clicar `Desligar follow-up`.
6. Confirmar flash de sucesso.
7. Recarregar a tela e validar que o botao desapareceu.
8. Rodar `php artisan credflow:check-followups` e confirmar que o lead nao e despachado.

### Validacao especifica na VPS

Antes do deploy:

1. Rodar a consulta de distribuicao de `followup_status`.
2. Separar um lead real de teste com ticket em `/atendimentos`.
3. Anotar `id`, `followup_status`, `followup_count`, `status`, `conversation_id`, `last_interaction_at` e `last_inbound_at`.

Depois do deploy:

1. Clicar `Desligar follow-up` nesse lead.
2. Confirmar no banco que `followup_status` virou `inactive`.
3. Confirmar que `followup_count` e os demais campos anotados nao mudaram.
4. Confirmar que o lead nao aparece mais em consultas do scheduler:

```sql
select id
from leads
where id = :lead_id
  and followup_status = 'active';
```

Essa consulta deve retornar zero linhas.

## Comandos de validacao

```powershell
php artisan test tests\Feature\ServiceTicketControllerTest.php tests\Feature\LeadFollowUpControllerTest.php tests\Feature\FollowUpEngineTest.php
npm run lint
```

Se o ambiente usa `uv`/wrapper local para testes, manter o padrao ja usado no projeto.

## Riscos e cuidados

- Nao zerar `followup_count`; ele e historico operacional.
- Nao alterar `last_interaction_at`; desligar follow-up nao e interacao com cliente.
- Nao usar `paused` para "desligar"; `paused` significa retomavel e e usado por fluxo humano/cache.
- Nao criar nova coluna antes de haver necessidade de auditar motivo/operador do desligamento.
- Se auditoria for requisito, registrar evento em `AgentInteractionEventService` ou criar metadata/evento em fase posterior.
- Confirmar a semantica com produto: "desligar" nesta entrega significa parar novas tentativas automaticas, nao bloquear IA receptiva nem campanhas futuras.

## Fase posterior recomendada

Criar um controle mais completo de follow-up por lead:

- motivo do desligamento;
- usuario que desligou;
- data/hora;
- opcao de reativar quando ainda houver janela valida;
- exibicao no historico/timeline;
- integracao com o futuro kanban operacional descrito em `docs/atendimento-lead-kanban-human-handoff-plan.md`.
