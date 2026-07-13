# Relatorio do modulo de follow-up

Atualizado em 2026-07-13. Reflete o codigo atual; a versao anterior citava componentes que nao existem mais (`aria:check-followups`, `AriaFollowUpAgent`, `WhatsAppService` direto, `zombie_cutoff_days`, exclusao por `campaign_id`).

## Fluxo

1. Scheduler roda `credflow:check-followups` a cada 5 minutos (`routes/console.php`).
2. `CheckFollowUpsCommand`:
   - desativa em massa leads ativos fora da janela de atendimento (24h desde `last_inbound_at` / `service_window_expires_at`, considerando tambem `free_entry_point_expires_at`);
   - alerta via `AlertService` se mais de 5 jobs da fila `followups` falharam na ultima hora;
   - varre leads `followup_status = active` com `last_inbound_at` preenchido OU free entry point ainda aberto em `chunkById` (tamanho em `credflow.followup.check_chunk_size`, env `FOLLOWUP_CHECK_CHUNK_SIZE`), avalia cada um com `FollowUpWindowService::evaluate()` e despacha `ProcessLeadFollowUpJob` para os elegiveis (jitter opcional em `credflow.jobs.cron_dispatch_jitter_seconds`).
3. `ProcessLeadFollowUpJob` (fila `followups`, unico por lead ate iniciar processamento, `tries=3`, `backoff=[60,300]`, `timeout=120`):
   - re-checa status ativo, elegibilidade e inbound recente (`credflow.followup.skip_if_recent_inbound_minutes`, default 30);
   - resolve a instancia WhatsApp ANTES de chamar o LLM — lead sem instancia utilizavel e desativado sem gastar chamada de agente;
   - claim por tentativa no cache (`followup_send:{lead}:{attempt}`) impede envio duplicado em retry;
   - gera a mensagem com `CredFlowFollowUpAgent` (retomando a conversa existente quando ha `conversation_id`);
   - envia via `WhatsappOutboxService::queueSplitTextForLead` → outbox com chave de idempotencia → `ProcessWhatsappOutboxMessageJob` (dispatch `afterCommit`) → provider Meta Cloud;
   - grava `followup_messages` (status `sent`), incrementa `followup_count` e atualiza `last_interaction_at` na MESMA transacao do enfileiramento do outbox;
   - resposta vazia/sentinela grava linha `no_reply`; falha permanente do job grava linha `failed` — ambas servem de piso de backoff para `nextDueAt()` sem consumir tentativa;
   - ao atingir o maximo de tentativas, desativa o follow-up.
4. Resposta inbound do cliente desativa o follow-up (`IncomingConversationPersister`, transacao ATOM-4).

## Resolucao de configuracao

`FollowUpSettingsResolver` (cache 300s por chave), primeira camada com linha vence:

1. `agent_followup_settings` (por agente) — escrita pela tela `/agentes/{agent}/follow-up`;
2. `agent_configs` legado (espelhado pela mesma tela para compatibilidade);
3. defaults: enabled, first_delay 10min, min_interval 60min, max 2 tentativas, janela 08:00–20:00 America/Sao_Paulo, message_type `contextual`, tone `consultivo`, persuasao 2.

A antiga camada por tenant (`followup_settings`) foi removida em 2026-07-13 (auditoria F4): nunca teve writer em producao. Tabela dropada por migration.

O prompt do `CredFlowFollowUpAgent` le os mesmos valores do resolver (max de tentativas, tipo, tom, persuasao, instrucoes custom), garantindo que `is_last`/despedida coincidam com o que o engine aplica. `agent_name` e `followup_approach` continuam vindo do `AgentConfig` legado (campos sem equivalente no resolver).

## Elegibilidade (`FollowUpWindowService::evaluate`)

Razoes de recusa, em ordem: `disabled`, `sandbox`, `not_active`, `human_paused` (modo manual efetivo — `ai_mode` com fallback no `default_ai_mode` da instancia —, estagio de handoff humano, IA pausada ou numero pausado no `PauseService`), `terminal_status`, `no_inbound_window`, `window_expired_requires_hsm`, `outside_business_hours`, `max_reached`, `first_delay_not_reached` / `interval_not_reached`. Janela overnight (fim < inicio, ex. 22:00→06:00) e suportada.

## Configuracao pela UI

`/agentes/{agent}/follow-up` (`AgentFollowUpController` + `UpdateAgentFollowUpRequest`): atraso da primeira tentativa, horario diario (campo legado de UI), maximo de tentativas, abordagem, janela (aceita overnight), intervalo em dias (vira `min_interval_minutes`), tipo de mensagem (inclui `contextual`, o default do engine), tom, persuasao e instrucoes custom.

## Requisitos de producao

1. `php artisan schedule:run` a cada minuto.
2. Worker na fila: `php artisan queue:work --queue=followups,default` (Horizon cobre).
3. Redis/cache funcional (unicidade de job, claim por tentativa, cache do resolver).
4. Lead com `whatsapp_instance_id` valido apontando para `whatsapp_instances` com provider Meta Cloud configurado.
5. Webhook inbound ativo para atualizar `last_inbound_at`/`service_window_expires_at` e encerrar follow-ups quando o cliente responde.
6. Lead armado com `followup_status = active` e `followup_count = 0` (armado na qualificacao pelas tools, na UI ou em bulk).

## Free entry point (F7)

Leads com free entry point aberto e sem `last_inbound_at` entram na varredura desde 2026-07-13. O `first_delay`/`min_interval` ancoram em `free_entry_point_started_at` quando nao ha inbound. Observacao: hoje o unico writer dos campos FEP e `markInbound` (que tambem preenche `last_inbound_at`), entao leads FEP-only so surgirao quando algum fluxo futuro (ex. campanha CTWA) armar o FEP sem inbound — o engine ja esta pronto para isso.
