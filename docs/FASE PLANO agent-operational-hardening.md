FASE PLANO agent-operational-hardening

FASES CONCLUIDAS: 0.1, 0.2, 1.1, 1.2, 2.1, 2.2
FASE ATUAL (em andamento?): 2.2 (n)
PROXIMA FASE: 3.1

Ultima atualizacao: Fase 2 concluida.

Entregas da Fase 1:
- Criada base `agent_interaction_events`, model e `AgentInteractionEventService`.
- Criado `AgentInteractionContext` scoped para compartilhar `interaction_id` entre `AgentService` e middlewares de IA.
- Fluxo receptivo Meta propaga `interaction_id` do webhook ao job, ao agente, ao fact-check, ao envio WhatsApp e ao broadcast frontend.
- Campanhas Meta/template registram despacho, fila, envio e falha.
- Follow-up registra inicio, skip, no-reply e envio.
- URA reversa e trigger de template registram entrada e envio.
- Acao manual do operador registra envio humano.
- Reprocessamento do laboratorio registra inicio, sucesso e falha.
- `AuditLogMiddleware` registra `model_called` e usa `interaction_id` como trace do Langfuse.
- `ToolCallGuardMiddleware` registra `tool_called` e inclui `interaction_id` nos logs de loop.
- Sentry recebe tag `interaction_id`.
- Laboratorio ganhou endpoints JSON para timeline por `interaction_id` e por lead.

Entregas da Fase 2:
- Criada base `conversation_timeline_messages`, model e `ConversationTimelineService` com contrato unico de mensagem para frontend.
- Criada base `whatsapp_outbox_messages`, model, `WhatsappOutboxService` e `ProcessWhatsappOutboxMessageJob`.
- Fluxo receptivo WhatsApp grava inbound na timeline e enfileira resposta da IA na outbox antes de envio real.
- Envio manual do operador grava timeline/outbox e retorna status `queued`.
- Follow-up automatico grava timeline/outbox antes do envio real.
- Outbox atualiza status da timeline para `sending`, `sent` ou `failed` e registra `provider_message_id`.
- Callback de status por `provider_message_id` sincroniza status da timeline quando houver outbox relacionada.
- Tela de conversa e preview passam a ler a timeline unificada em vez da memoria interna do agente.
- Limpeza de historico remove tambem a timeline operacional do lead.
- Testes adicionados/ajustados para timeline, outbox, envio manual, inbound e follow-up.

Proxima fase: 3.1, endurecer fact-check para falhar fechado em respostas financeiras nao validadas.
