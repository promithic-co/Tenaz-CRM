# Plano de implementacao - Follow-up dentro da janela Meta

Data: 2026-05-10
Escopo: planejamento tecnico para atualizar o modulo de follow-up antes da execucao da implementacao.

## Objetivo

Enquadrar o modulo de follow-up como um motor de aproveitamento da janela aberta do WhatsApp, nao como campanha de reengajamento de varios dias.

O follow-up deve:

- Enviar mensagens livres apenas enquanto a janela Meta estiver aberta.
- Usar como base a ultima mensagem inbound do cliente: `last_inbound_at + 24 horas`.
- Operar na pratica em leads com mensagem do mesmo dia ou do dia anterior, desde que ainda estejam dentro das 24 horas exatas.
- Ajudar a concluir a negociacao enquanto o cliente ainda pode receber mensagem contextual sem template.
- Manter IA contextual, mas com configuracao mais enxuta e padrao por tenant/conta, nao individual por agente.
- Expor um dashboard operacional como primeira tela do modulo.

## Regra Meta considerada

Em maio/2026, a regra operacional relevante para WhatsApp Business Platform e:

- A janela de atendimento e movel, iniciada ou renovada por mensagem do cliente.
- Dentro da janela de 24 horas a empresa pode enviar mensagem livre/contextual.
- Apos 24 horas sem nova mensagem do cliente, mensagem livre deve ser bloqueada; para reabrir conversa e necessario template aprovado.
- Desde 2025-07-01, a cobranca passou a ser por mensagem/template entregue em grande parte dos cenarios; templates utilitarios dentro da janela tendem a ter tratamento diferente de templates fora da janela. Isso afeta custo, mas a regra de envio livre continua dependendo da janela de 24 horas.

Fontes consultadas na pesquisa previa:

- Twilio changelog sobre atualizacao Meta em 2025-07-01: https://www.twilio.com/en-us/changelog/meta-is-updating-whatsapp-pricing-on-july-1--2025
- Vonage sobre customer care window: https://api.support.vonage.com/hc/en-us/articles/23794412588572-What-is-the-24-Hour-Customer-Care-Window
- Infobip sobre free-form messages: https://www.infobip.com/docs/whatsapp/message-types-and-templates/free-form-messages
- Zapier/Respond.io usados como referencia complementar de operacao e pricing.

## Fluxo de negocio da plataforma

Fluxo atual esperado:

1. Campanhas de prospeccao via Meta HSM e URA.
2. Cliente responde e abre/renova janela.
3. IA qualifica e vende automaticamente.
4. Se o cliente para de responder, follow-up contextual tenta concluir a negociacao dentro da janela.
5. Quando necessario, escala para atendente humano.
6. Humano pode intervir a qualquer momento.

Implica que follow-up deve nascer de uma conversa ja iniciada pelo cliente ou de resposta a template/campanha, nao de um disparo livre fora da janela.

## Auditoria do codigo atual

### Scheduler e motor

Arquivos atuais:

- `routes/console.php`
- `app/Console/Commands/CheckFollowUpsCommand.php`
- `app/Jobs/ProcessLeadFollowUpJob.php`
- `app/Ai/Agents/CredFlowFollowUpAgent.php`

Comportamento encontrado:

- `routes/console.php` agenda `credflow:check-followups` a cada 5 minutos.
- `CheckFollowUpsCommand` busca `Lead::where('followup_status', 'active')`.
- O comando exclui leads com `campaign_id` usando `whereNull('campaign_id')`.
- O disparo usa `last_interaction_at` para calcular atraso inicial e intervalo entre tentativas.
- O comando atualiza `last_interaction_at` antes de despachar o job, para evitar reagendamento.
- `ProcessLeadFollowUpJob` ja usa `last_inbound_at` apenas como race guard de inbound recente.
- O job nao valida a janela Meta de 24h antes de gerar IA/enfileirar outbox.
- O agente de follow-up ainda tem logica de inatividade de mais de 7 dias, o que contradiz o novo enquadramento.

Atualizacoes necessarias:

- Criar regra central para janela: `last_inbound_at` obrigatorio e `now <= last_inbound_at + 24h`.
- Remover a dependencia de `last_interaction_at` como permissao de envio. Ele pode continuar para UI/ordenacao, mas nao para janela Meta.
- Substituir intervalos em dias por intervalos em minutos/horas dentro da mesma janela.
- Remover `whereNull('campaign_id')` ou substituir por regra mais correta: campanhas podem entrar em follow-up se o cliente respondeu e abriu janela.
- Fazer `ProcessLeadFollowUpJob` repetir a validacao da janela antes de chamar IA, como protecao contra jobs atrasados.
- Registrar skip com motivo estruturado: `no_inbound_window`, `window_expired`, `recent_inbound`, `human_paused`, `terminal_status`, `max_reached`, `outside_business_hours`.

### Lead e timestamps

Arquivos atuais:

- `app/Models/Lead.php`
- `database/migrations/2026_03_02_153213_create_leads_table.php`
- `database/migrations/2026_04_09_012416_add_last_inbound_at_to_leads_table.php`

Campos atuais:

- `followup_count`
- `followup_status`
- `last_interaction_at`
- `last_inbound_at`
- `campaign_id`
- `status`
- `agent_id`
- `tenant_id`

Comportamento encontrado:

- `Lead::activateFollowUp()` ativa follow-up e atualiza `last_interaction_at`, mas nao considera `last_inbound_at`.
- `AgentService::process()` atualiza `last_interaction_at` e `last_inbound_at` quando processa inbound normal.
- `ProcessPausedIncomingMessageJob` atualiza `last_interaction_at` e `last_inbound_at`, mas nao desativa follow-up ativo.
- Tools de qualificacao ativam follow-up quando o status vira `qualificado`.

Atualizacoes necessarias:

- Criar helpers no model ou service dedicado:
  - `customerServiceWindowClosesAt()`
  - `isInsideCustomerServiceWindow()`
  - `customerServiceWindowRemainingMinutes()`
  - `followUpWindowDayScope()` se necessario para filtros de hoje/ontem.
- `activateFollowUp()` deve ativar apenas se existe `last_inbound_at` dentro da janela ou registrar/retornar bloqueio.
- Pausa humana e inbound em conversa pausada devem encerrar ou pausar follow-up automatico.
- Status finais (`optou_sair`, `convertido`, `escalado`, possivelmente `desqualificado`) devem bloquear follow-up.

### Configuracao

Arquivos atuais:

- `app/Http/Controllers/AgentFollowUpController.php`
- `app/Http/Requests/UpdateAgentFollowUpRequest.php`
- `app/Services/AgentConfigResolver.php`
- `app/Models/AgentConfig.php`
- `app/Models/AppSetting.php`
- `database/migrations/2026_03_14_010619_create_agent_configs_table.php`
- `database/migrations/2026_03_16_201721_add_followup_schedule_to_agent_configs_table.php`
- `database/migrations/2026_05_03_000001_add_followup_customization_to_agent_configs_table.php`

Comportamento encontrado:

- Configuracao real de follow-up esta em `agent_configs`, uma linha por agente.
- `UpdateAgentFollowUpRequest` permite `followup_interval_days` em `1,2,3,5,7`.
- A UI mostra "Follow-ups do Dia Seguinte" e intervalos de varios dias.
- `AgentConfigResolver` resolve por agente e cai para `AppSetting` legado.
- `AppSetting` e escopado por `user_id`, nao por `tenant_id`; para multi-tenant atual, isso nao e ideal como nova fonte oficial.

Decisao proposta:

- Criar nova tabela/model tenant-scoped: `followup_settings`.
- Usar uma configuracao padrao por tenant/conta para todos os agentes.
- Manter fallback temporario para `agent_configs` durante migracao.
- Nao remover colunas antigas de `agent_configs` nesta entrega; marcar como deprecated e parar de usa-las no modulo novo.

Schema proposto para `followup_settings`:

- `id`
- `tenant_id` string, unique/index
- `enabled` boolean default true
- `first_delay_minutes` unsignedSmallInteger default 10
- `min_interval_minutes` unsignedSmallInteger default 60
- `max_attempts_within_window` unsignedTinyInteger default 2 ou 3
- `business_window_start` time default `08:00`
- `business_window_end` time default `20:00`
- `timezone` string default `America/Sao_Paulo`
- `message_type` string default `contextual`
- `tone` string default `consultivo`
- `persuasion_intensity` unsignedTinyInteger default 2
- `custom_instructions` text nullable
- `created_at`, `updated_at`

Campos a evitar no novo modulo:

- `daily_time`
- `followup_interval_days`
- opcoes de 2/3/5/7 dias
- configuracao individual por agente como padrao operacional

Arquivos novos previstos:

- `app/Models/FollowUpSetting.php`
- `database/migrations/*_create_followup_settings_table.php`
- `app/Services/FollowUpSettingsResolver.php`
- `app/Http/Requests/UpdateFollowUpSettingsRequest.php`

### IA contextual

Arquivo atual:

- `app/Ai/Agents/CredFlowFollowUpAgent.php`

Comportamento encontrado:

- O agente usa historico da conversa.
- Pode chamar ferramentas de consulta de credito, escalamento e opt-out.
- O prompt fala em "reengajamento" e ciclo autonomo.
- O prompt tem regras para leads inativos ha mais de 7 dias.

Atualizacoes necessarias:

- Trocar framing de "reengajamento" para "continuidade dentro da janela aberta".
- Incluir no prompt:
  - tempo restante da janela;
  - ultima mensagem inbound;
  - estagio do lead;
  - proposta/credito disponivel;
  - ultima pergunta/objecao;
  - se esta e a ultima tentativa dentro da janela.
- Remover orientacao de follow-up para varios dias.
- Reforcar que nao deve prometer disponibilidade fora da janela.
- Manter limite de texto curto e nao repeticao.
- Evitar consulta externa se ja existe credito recente suficiente, para reduzir custo/latencia.

### Entradas que ativam follow-up

Arquivos atuais:

- `app/Ai/Tools/ConsultarCreditoInssTool.php`
- `app/Ai/Tools/ConsultarCreditoSiapeTool.php`
- `app/Ai/Tools/AtualizarStatusLeadTool.php`
- `app/Jobs/SendUraTemplateJob.php`
- `app/Jobs/SendInboundLeadWhatsAppJob.php`
- `app/Jobs/ProcessIncomingWhatsAppMessageJob.php`
- `app/Services/CampaignReplyDetector.php`

Comportamento encontrado:

- Tools de consulta ativam `followup_status = active` quando lead vira `qualificado`.
- `AtualizarStatusLeadTool` ativa follow-up quando status vira `qualificado`.
- `SendUraTemplateJob` cria lead com `followup_status = active` antes de haver resposta inbound.
- `SendInboundLeadWhatsAppJob` cria lead, mas nao ativa follow-up.
- `ProcessIncomingWhatsAppMessageJob` cancela follow-up ativo quando cliente responde.
- `CampaignReplyDetector` vincula lead a campanha, mas `CheckFollowUpsCommand` exclui `campaign_id`.

Atualizacoes necessarias:

- Nao ativar follow-up automaticamente apenas porque um template de URA foi enviado. O follow-up livre so pode iniciar depois da resposta inbound do cliente.
- Quando a IA qualificar o lead apos inbound, ativar follow-up se a janela ainda estiver aberta.
- Permitir follow-up para leads vindos de campanha quando existe `last_inbound_at` valido.
- Se cliente responder durante sequencia, desativar follow-up e deixar a IA normal assumir.
- Se atendimento humano assumir, pausar/desativar follow-up.

### Frontend e navegacao

Arquivos atuais:

- `resources/js/components/AppSidebar.vue`
- `resources/js/pages/agente/follow-up/Index.vue`
- `routes/web.php`
- `resources/js/routes/*` gerados por Wayfinder

Comportamento encontrado:

- O item `Follow-up` fica dentro de `Agentes`.
- O clique abre `/agente/follow-up`, que redireciona para `/agentes/{agent}/follow-up`.
- A tela inicial e configuracao, nao dashboard.
- Breadcrumb atual trata follow-up como configuracao do agente.

Atualizacoes necessarias:

- Criar rota principal `GET /follow-up` para dashboard.
- Criar rota `GET /follow-up/configuracoes` para configuracao global.
- `POST` ou `PUT /follow-up/configuracoes` para salvar `followup_settings`.
- Manter `/agente/follow-up` como redirect legado para `/follow-up`.
- Manter `/agentes/{agent}/follow-up` temporariamente como redirect ou tela de aviso, para nao quebrar links.
- Atualizar `AppSidebar.vue` para o item `Follow-up` apontar para `/follow-up`.
- Reposicionar Follow-up como modulo operacional. Pode continuar no grupo Agentes por agora, mas o ideal e virar item de primeiro nivel ou subitem operacional fora de configuracao de agente.

Arquivos novos previstos:

- `app/Http/Controllers/FollowUpController.php`
- `resources/js/pages/follow-up/Index.vue`
- `resources/js/pages/follow-up/Settings.vue`

### Dashboard de follow-up

Feedback de produto:

Faz sentido a entrada do modulo abrir um dashboard, desde que seja operacional e enxuto. O follow-up existe para aproveitar uma janela curta; portanto o dashboard deve mostrar decisao e acao, nao analise decorativa.

Dados uteis:

- Janelas abertas agora.
- Janelas expirando nos proximos 30/60/120 minutos.
- Leads ativos aguardando proximo follow-up.
- Follow-ups enviados hoje.
- Respostas apos follow-up hoje.
- Conversoes/escalamentos apos follow-up.
- Leads bloqueados por janela expirada.
- Falhas de envio/provider.

Dados a evitar nesta primeira entrega:

- Graficos longos por agente.
- Ranking de tom/persuasao.
- Historico de muitos dias como primeira dobra.
- Metrica sem acao operacional clara.

Queries/fontes sugeridas:

- `leads.followup_status = active`
- `leads.last_inbound_at >= now() - 24h`
- `leads.last_inbound_at + 24h` para countdown
- `followup_messages.sent_at`
- `conversation_timeline_messages.source = followup`
- `conversation_timeline_messages.status in queued,sending,sent,failed`
- `whatsapp_outbox_messages.status`
- `service_tickets`/`leads.status = escalado`

### Conversas e controle humano

Arquivos atuais:

- `app/Http/Controllers/ConversasController.php`
- `resources/js/pages/conversas/Show.vue`
- `app/Http/Controllers/LeadFollowUpController.php`
- `app/Jobs/ProcessPausedIncomingMessageJob.php`
- `app/Services/PauseService.php`

Atualizacoes necessarias:

- Mostrar no lead o tempo restante da janela quando `last_inbound_at` existir.
- Pausar/desativar follow-up quando operador humano pausar o agente ou enviar mensagem manual.
- `ProcessPausedIncomingMessageJob` deve cancelar follow-up ativo ao receber inbound enquanto humano assumiu.
- Botao "Retomar Follow-up" deve validar se ainda ha janela aberta; se nao houver, mostrar bloqueio/erro amigavel.

### Observabilidade e registros

Arquivos atuais:

- `app/Services/AgentInteractionEventService.php`
- `app/Jobs/ProcessLeadFollowUpJob.php`
- `app/Console/Commands/CheckFollowUpsCommand.php`
- `app/Http/Controllers/LaboratoryController.php`

Atualizacoes necessarias:

- Registrar `followup_skipped` com motivos padronizados.
- Registrar `window_expires_at` e `remaining_minutes` no payload dos eventos.
- Registrar `followup_queued`, `followup_sent`, `followup_failed` via outbox/timeline.
- Atualizar metricas do Laboratory ou do novo dashboard para separar:
  - ativo dentro da janela;
  - ativo sem janela;
  - expirado/desativado;
  - bloqueado por humano/status.

### Testes impactados

Arquivos atuais:

- `tests/Feature/FollowUpEngineTest.php`
- `tests/Feature/FollowupMessageTest.php`
- `tests/Feature/LeadFollowUpControllerTest.php`
- `tests/Feature/AgentConfigTest.php`
- `tests/Feature/Phase43/FollowUpRegressionTest.php`
- `tests/Feature/ConversationTimelineOutboxTest.php`

Testes a atualizar/remover:

- Testes que esperam follow-up diario por `daily_time`.
- Testes que esperam `followup_interval_days` de 2/3/5/7.
- Testes que criam lead sem `last_inbound_at` e esperam dispatch.
- Testes de configuracao por agente como comportamento principal.

Testes novos:

- Dispara primeiro follow-up quando `last_inbound_at` esta dentro de 24h e delay minimo foi atingido.
- Nao dispara se `last_inbound_at` e nulo.
- Nao dispara se `last_inbound_at + 24h` expirou.
- Dispara caso "ontem" ainda dentro de 24h.
- Nao dispara caso "hoje" mas ja passou de 24h em timezone/clock controlado.
- Nao dispara fora da janela horaria operacional, mesmo dentro da janela Meta.
- Nao dispara se lead esta `escalado`, `convertido`, `optou_sair`.
- Nao dispara se agente/lead esta em pausa humana.
- Permite lead com `campaign_id` quando houve inbound valido.
- Nao ativa follow-up em `SendUraTemplateJob` antes de resposta inbound.
- Retomar follow-up pela conversa falha se janela expirou.
- Dashboard retorna contadores corretos e lista ordenada por menor tempo restante.

## Sequencia de implementacao proposta

### Fase 1 - Infra de regra e configuracao

1. Criar `followup_settings`.
2. Criar `FollowUpSettingsResolver`.
3. Criar service pequeno para janela, por exemplo `FollowUpWindowService`.
4. Adicionar helpers ao `Lead` apenas se nao duplicarem regra do service.
5. Backfill inicial por tenant usando:
   - config do agente default quando existir;
   - fallback para defaults conservadores.

### Fase 2 - Motor de elegibilidade

1. Refatorar `CheckFollowUpsCommand` para usar o service de elegibilidade.
2. Remover intervalos em dias.
3. Permitir campanhas com inbound valido.
4. Desativar/registrar expirados.
5. Revalidar janela em `ProcessLeadFollowUpJob` antes de IA/outbox.
6. Ajustar eventos e logs.

### Fase 3 - IA e ativacao correta

1. Atualizar `CredFlowFollowUpAgent`.
2. Ajustar tools de qualificacao para ativarem apenas quando existe janela valida.
3. Ajustar URA/template para nao ativar follow-up antes de inbound.
4. Ajustar inbound pausado e controle humano.

### Fase 4 - UI

1. Criar dashboard `/follow-up`.
2. Criar settings `/follow-up/configuracoes`.
3. Atualizar sidebar e breadcrumbs.
4. Deixar rotas antigas como redirect.
5. Atualizar tela de conversa para mostrar janela/restante quando util.

### Fase 5 - Testes e validacao

1. Atualizar testes existentes.
2. Criar testes de janela de 24h.
3. Criar testes de dashboard/config global.
4. Rodar suite focada:
   - `uv run --extra dashboard pytest tests/test_dashboard_api.py tests/test_scoring.py` se aplicavel ao contexto Python existente.
   - Para Laravel, rodar `php artisan test --filter=FollowUp` ou Pest equivalente no ambiente do projeto.
5. Fazer validacao manual com lead real/sandbox:
   - inbound abre janela;
   - qualificacao ativa follow-up;
   - follow-up envia dentro da janela;
   - resposta do cliente encerra follow-up;
   - expirado bloqueia envio livre.

## Criterios de aceite

- Nenhum follow-up livre e enviado fora de `last_inbound_at + 24h`.
- Follow-up de campanha funciona quando o cliente respondeu e abriu janela.
- URA/template nao cria follow-up livre antes de resposta do cliente.
- Configuracao principal e unica por tenant/conta.
- Entrada `Follow-up` abre dashboard operacional.
- Tela de configuracao fica em subrota.
- Rotas antigas continuam sem erro, por redirect.
- Eventos/logs indicam por que cada lead foi enviado ou ignorado.
- Testes cobrem mesmo dia, dia anterior dentro da janela, janela expirada, humano, opt-out e campanha.

## Riscos e decisoes pendentes

- Confirmar se o produto quer `max_attempts_within_window` default 2 ou 3. Minha recomendacao inicial: 2 para reduzir pressao e custo.
- Confirmar se o follow-up deve ficar como item principal do sidebar ou subitem de Agentes. Minha recomendacao: item principal, pois deixou de ser configuracao de agente.
- Confirmar se status `desqualificado` deve bloquear sempre. Minha recomendacao: bloquear.
- Confirmar se deve haver reabertura via template quando janela expira. Minha recomendacao: fora desta entrega; isso e outro modulo de reengajamento/template, nao follow-up livre.

