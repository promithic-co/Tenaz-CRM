# Fase 0 - Linha de Base e Criterios de Sucesso

Este documento executa a Fase 0 do plano `agent-operational-hardening-plan.md`: mapear os fluxos reais, os arquivos envolvidos, os eventos/persistencias existentes e as metricas que passam a ser usadas como criterio comum de confiabilidade operacional.

## Resumo da linha de base

O sistema ja tem uma base funcional para atendimento receptivo, campanhas, follow-up, URA, auditoria de IA e laboratorio. O ponto fraco principal ainda e a fragmentacao: a conversa visivel no frontend vem de `agent_conversation_messages`, campanhas usam tabelas proprias, follow-ups usam `followup_messages`, envios diretos passam por `WhatsAppService` e os logs estruturados nao compartilham um `interaction_id`.

A prioridade tecnica do Release 1 deve ser criar uma trilha unica para cada turno: entrada, IA, ferramentas, fact-check, envio, broadcast e status de entrega.

## Fluxos reais mapeados

| Fluxo | Comeco | Processador/job | Onde salva mensagem/estado | Onde salva custo | Onde falha | Onde aparece hoje |
| --- | --- | --- | --- | --- | --- | --- |
| Lead entra pelo WhatsApp e recebe resposta IA | `WhatsAppWebhookController::handle` ou `MetaWebhookController::handle` | `ProcessIncomingWhatsAppMessageJob` na fila `messages` | `leads`, `agent_conversations`, `agent_conversation_messages`; broadcast `NewConversationMessage` | `AuditLogMiddleware` dispara `LogAiUsageJob` para `ai_usage_dailies` | logs `whatsapp.*`, `meta.*`, `aria.agent_error`, `failed_interactions` via `InteractionRecoveryService` | `/conversas`, dashboard e laboratorio de falhas/uso IA |
| Lead vem de campanha de template e responde | Webhook Meta/Evolution recebe inbound do mesmo telefone | `ProcessIncomingWhatsAppMessageJob`; `CampaignReplyDetector::detect` vincula contexto | `leads.campaign_id` quando detectado; conversa IA em `agent_conversation_messages` | `ai_usage_dailies` se IA responder | detector pode nao vincular se nao encontrar campanha ativa; webhook/job seguem logs padrao | `/conversas`, campanhas e dashboard |
| Lead vem de follow-up automatico | Scheduler/comando de follow-up despacha lead ativo | `ProcessLeadFollowUpJob` na fila `followups` | `followup_messages`, `leads.followup_count`, `leads.followup_status`, conversa no provider de IA quando houver `conversation_id` | custo do LLM pelo middleware da chamada do agente | logs `ProcessLeadFollowUpJob:*`; falha permanente no `failed()` | historico de follow-up em `/conversas/{lead}` e laboratorio follow-up |
| Lead vem da URA reversa e cai no WhatsApp | `UraInboundController::store` ou `UraInboundController::trigger` | `SendInboundLeadWhatsAppJob` ou `SendUraTemplateJob` na fila `messages` | `leads`; envio direto via `WhatsAppService`; URA trigger ativa `followup_status` | sem custo IA nesse envio inicial | logs `ura.*`; falhas de instancia/template apenas logam e retornam | modulo URA, lead em conversas quando criado |
| Agente consulta CPF e gera proposta | Agent decide chamar tool `ConsultarCreditoInssTool`/`ConsultarCreditoSiapeTool` | Tool executada dentro do turno do agente | `leads.cpf`, `leads.credito_json`, `leads.status`, `followup_status` | custo IA em `ai_usage_dailies`; custo de API externa ainda nao contabilizado em creditos | circuit breaker por cache, logs `aria.tool.*`, retorno `ToolResult::error` | conversa e status do lead; laboratorio via logs/stress tests |
| Agente escala para humano | Tool `EscalarParaHumanoTool` | Execucao dentro do turno do agente | `service_tickets`, `leads.status = escalado`, `followup_status = inactive` | custo IA do turno | logs `aria.tool.escalar_erro`; transacao DB protege ticket/status | `/atendimentos`, `/conversas`, dashboard |
| Humano assume conversa e agente para | Operador envia mensagem propria ou pausa no frontend | `OperatorCommandService` no webhook `fromMe`; `ConversasController::pause` | estado de pausa em `PauseService`; mensagem manual entra em `agent_conversation_messages` se houver `conversation_id` | nao ha custo IA | logs `whatsapp.from_me`; se pausado, inbound vai para `ProcessPausedIncomingMessageJob` | `/conversas` mostra pausado e mensagens manuais quando ha conversa |
| Tenant fica sem credito | Ainda nao ha modelo de creditos por tenant | Nao implementado | `ai_usage_dailies` mede depois da chamada, nao bloqueia antes | `ai_usage_dailies` | risco atual: nao existe preflight de credito | laboratorio de uso IA apenas mostra consumo |
| Meta/API externa falha | Webhook/status Meta, envio campanha ou tool externa | `SendCampaignMessageJob`, `ProcessCampaignDeliveryEventJob`, tools e providers | `campaign_messages.status/error_*`, logs e contadores de campanha | custos IA separados; API externa sem creditos | logs `meta.*`, `SendCampaignMessageJob`, `aria.tool.*`; auto-pause de campanha por erro | campanhas, laboratorio health/falhas |
| Fact-check reprova resposta | `AgentService::applyFactCheckGuardrail` depois da resposta IA | retry no mesmo agente; na segunda reprova escala lead | `leads.status = escalado` se falhar duas vezes | custo dos turnos de IA em `ai_usage_dailies` | logs `aria.fact_check_retry`, `aria.fact_check_failed_twice`; timeout hoje permite resposta original | conversa e logs; laboratorio de testes/fact-check |
| Cliente pede opt-out | Status do lead vira `optou_sair` por fluxo/tool/regra operacional | `AgentService::process` retorna `null` se status ja for opt-out | `leads.status`; envio e resposta param | sem custo adicional se bloqueado antes | risco atual: origem exata do opt-out nao esta centralizada em timeline | `/conversas` por status/filtro |

## Tabelas e contratos de status

### Lead

Fonte principal: `leads.status`, `leads.modo`, `leads.followup_status`.

| Campo | Valores observados/esperados | Uso operacional |
| --- | --- | --- |
| `status` | `novo`, `qualificado`, `sem_credito`, `desqualificado`, `escalado`, `optou_sair` | funil comercial, pausa de IA por opt-out, abertura de atendimento humano |
| `modo` | `receptivo` | origem operacional ampla do lead |
| `followup_status` | `inactive`, `active`, `paused` | controle do motor de follow-up |

### Conversa/mensagem

Fonte atual: `agent_conversations` e `agent_conversation_messages` da biblioteca de IA.

| Campo atual | Valores observados | Lacuna para Release 1 |
| --- | --- | --- |
| `role` | `user`, `assistant`, `operator` | nao diferencia fonte (`campaign`, `followup`, `manual`, `webhook`) nem status de envio |
| `attachments` | JSON com `_aria_media` quando ha midia | nao ha contrato unico para status, provider id e `interaction_id` |

### Campanha Meta/template

Fonte: `campaigns` e `campaign_messages`.

| Entidade | Campo | Valores |
| --- | --- | --- |
| `campaigns` | `status` | `draft`, `scheduled`, `sending`, `paused`, `completed`, `failed` |
| `campaign_messages` | `status` | `pending`, `queued`, `sent`, `delivered`, `read`, `failed` |

### Campanha Evolution livre

Fonte: `evolution_campaigns` e `evolution_campaign_messages`.

| Entidade | Campo | Valores |
| --- | --- | --- |
| `evolution_campaigns` | `status` | `draft`, `sending`, `paused`, `completed`, `failed` |
| `evolution_campaign_messages` | `status` | `pending`, `queued`, `sent`, `failed` |

### Atendimento humano/ticket

Fonte: `service_tickets`.

| Campo | Valores observados/esperados |
| --- | --- |
| `type` | `escalation` |
| `status` | `open` |
| `reason` | `proposta_aceita`, `solicitacao_cliente`, `problema_tecnico`, `outro` |

## Metricas oficiais da Fase 0

| Metrica | Definicao/formula | Origem dos dados | Frequencia minima |
| --- | --- | --- | --- |
| Taxa de resposta de campanha | leads que responderam / mensagens de campanha enviadas | `campaign_messages`, `evolution_campaign_messages`, `leads.campaign_id`, detector de resposta | diaria e por campanha |
| Taxa de qualificacao | leads com `status = qualificado` / leads consultados ou criados no periodo | `leads.status`, `leads.created_at`, `leads.last_interaction_at` | diaria |
| Taxa de escalonamento humano | leads/tickets escalados / conversas com IA no periodo | `service_tickets`, `leads.status`, futuro `agent_interaction_events` | diaria e por agente |
| Taxa de respostas bloqueadas por fact-check | bloqueios de fact-check / respostas IA de alto risco | hoje logs `aria.fact_check_*`; futuro `agent_interaction_events` | horaria no laboratorio |
| Taxa de erro por ferramenta | chamadas com `ToolResult::error` / total de chamadas por ferramenta | hoje logs `aria.tool.*`; futuro eventos `tool_called`, `tool_failed` | horaria |
| Tokens por tenant/agente/modelo | soma de `total_prompt_tokens` e `total_completion_tokens` | `ai_usage_dailies` | diaria |
| Custo por tenant/agente/modelo | soma de `estimated_cost_usd` | `ai_usage_dailies` | diaria |
| Custo por campanha | custo IA atribuido a leads da campanha / campanha | requer `interaction_id` ou `campaign_id` no uso IA; hoje parcial por lead/campanha | diaria apos Fase 1 |
| Custo por lead qualificado | custo total do periodo / leads qualificados no periodo | `ai_usage_dailies` + `leads.status` | diaria |
| Tempo medio de primeira resposta | `outbound_sent_at - inbound_received_at` do primeiro turno | requer timeline/outbox; hoje nao confiavel | horaria apos Fase 2 |
| Tempo medio de ferramenta externa | duracao entre `tool_called` e `tool_succeeded/tool_failed` | futuro `agent_interaction_events`; hoje apenas logs dispersos | horaria apos Fase 1 |
| Conversas com loop de ferramenta | interacoes com teto total/per-tool excedido | `ToolCallGuardMiddleware`, logs `aria.tool_guard.*`; futuro evento `tool_loop_blocked` | horaria |
| Taxa de falha Meta/API | mensagens `failed` / mensagens enviadas | `campaign_messages`, webhooks Meta, logs provider | horaria e por campanha |
| Opt-out por campanha/template | leads `optou_sair` vinculados / mensagens enviadas | `leads.status`, `campaign_messages`, templates | diaria |

## Criterios de sucesso adotados

1. Toda metrica acima deve ter numerador, denominador, filtro temporal e fonte definidos antes de virar card no laboratorio.
2. Qualquer bug de conversa deve ser investigavel a partir de telefone, lead ou provider message id ate chegar no turno de IA correspondente.
3. Toda saida real para WhatsApp deve ser rastreavel ate uma mensagem interna ou registro de campanha/follow-up.
4. Respostas financeiras sem validacao confiavel devem ser tratadas como risco operacional alto.
5. Custos de IA medidos depois da chamada sao linha de base; bloqueio por credito so sera criterio de aceite a partir da Fase 4.

## Lacunas confirmadas para iniciar Fase 1

| Lacuna | Evidencia atual | Proxima acao |
| --- | --- | --- |
| Falta `interaction_id` ponta a ponta | logs e jobs usam `lead_id`, `phone`, `campaign_id` ou `provider_message_id`, mas nao um identificador unico do turno | criar contexto operacional e propagar por webhook, jobs, `AgentService`, middleware e envio |
| Falta tabela de eventos do agente | informacao esta em logs, tabelas de campanha, `ai_usage_dailies` e `failed_interactions` | criar `agent_interaction_events` com service leve |
| Timeline do frontend e incompleta | `/conversas` le `agent_conversation_messages`; campanhas/follow-ups/envios diretos nao entram no mesmo contrato | criar `ConversationTimelineService` na Fase 2 |
| Envios ainda sao diretos | jobs chamam `WhatsAppService`/providers diretamente | criar outbox na Fase 2 |
| Custo nao faz preflight | `LogAiUsageJob` agrega apos resposta | implementar reserve/commit/refund na Fase 4 |
| Fact-check timeout ainda pode enviar resposta original | `AgentService::applyFactCheckGuardrail` retorna texto original no timeout | endurecer na Fase 3.1 |

## Arquivos consultados

- `app/Http/Controllers/WhatsAppWebhookController.php`
- `app/Http/Controllers/MetaWebhookController.php`
- `app/Http/Controllers/UraInboundController.php`
- `app/Http/Controllers/ConversasController.php`
- `app/Jobs/ProcessIncomingWhatsAppMessageJob.php`
- `app/Jobs/ProcessLeadFollowUpJob.php`
- `app/Jobs/SendInboundLeadWhatsAppJob.php`
- `app/Jobs/SendUraTemplateJob.php`
- `app/Jobs/DispatchCampaignJob.php`
- `app/Jobs/SendCampaignMessageJob.php`
- `app/Jobs/SendEvolutionCampaignMessageJob.php`
- `app/Jobs/ProcessCampaignDeliveryEventJob.php`
- `app/Services/AgentService.php`
- `app/Ai/Middleware/AuditLogMiddleware.php`
- `app/Ai/Middleware/TokenBudgetMiddleware.php`
- `app/Ai/Middleware/ToolCallGuardMiddleware.php`
- `app/Ai/Tools/ConsultarCreditoInssTool.php`
- `app/Ai/Tools/EscalarParaHumanoTool.php`
- `app/Models/Lead.php`
- `app/Models/Campaign.php`
- `app/Models/CampaignMessage.php`
- `app/Models/EvolutionCampaign.php`
- `app/Models/EvolutionCampaignMessage.php`
- `app/Models/AiUsageDaily.php`
