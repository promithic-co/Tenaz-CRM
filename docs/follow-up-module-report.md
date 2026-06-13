# Relatorio do modulo de follow-up

## Estado atual

O follow-up automatico esta implementado como um fluxo por lead e por agente:

- `aria:check-followups` avalia leads com `followup_status = active`.
- `ProcessLeadFollowUpJob` gera a mensagem com `AriaFollowUpAgent`.
- `WhatsAppService` envia a mensagem pela instancia WhatsApp resolvida para o lead/agente.
- `followup_messages` registra tentativa, texto, tom e status enviado.
- respostas recebidas pelo cliente desativam o follow-up em `ProcessIncomingWhatsAppMessageJob`.

O envio ja passa pela abstracao atual de WhatsApp. Quando a instancia existe em `whatsapp_instances`, `WhatsAppService` delega para o provider configurado:

- `EvolutionProvider`, para Evolution API.
- `MetaCloudProvider`, para WhatsApp Cloud API oficial.

Se a instancia nao for encontrada no banco, ainda existe fallback legado para Evolution via `services.evolution.*`.

## Configuracao disponivel

A tela de configuracao de follow-up fica em:

- `/agentes/{agent}/follow-up`, configuracao real por agente.
- `/agente/follow-up`, rota legada que redireciona para o agente padrao/primeiro agente do usuario.

Tambem foi adicionado um item `Follow-up` abaixo do menu `Agentes`.

Campos configuraveis:

- atraso da primeira tentativa em minutos;
- horario diario de envio;
- janela permitida de envio;
- intervalo em dias entre tentativas;
- quantidade maxima de tentativas;
- abordagem geral;
- tipo de mensagem;
- tom de voz;
- intensidade de persuasao;
- instrucoes adicionais para o prompt.

## Integracao 100% com a stack

Para operar em producao, a stack precisa garantir estes pontos:

1. Scheduler do Laravel rodando a cada minuto:
   `php artisan schedule:run`

2. Queue worker ouvindo a fila de follow-up:
   `php artisan queue:work --queue=followups,default`

3. Redis/cache funcional para `Cache::lock`, evitando disparo duplicado.

4. Instancia WhatsApp vinculada ao agente em `whatsapp_instances.agent_id`.

5. Webhook inbound ativo para a mesma instancia. Isso e necessario para atualizar `last_inbound_at`, encerrar follow-ups quando o cliente responde e evitar envio logo apos uma mensagem recebida.

6. Provider configurado por instancia:
   - Evolution: `provider = evolution`, `api_url`, `api_key`, `name`.
   - Meta Cloud: `provider = meta_cloud`, `meta_phone_number_id`, `meta_access_token`.

7. Leads qualificados precisam ter:
   - `agent_id`;
   - `tenant_id`;
   - `whatsapp`;
   - `followup_status = active`;
   - `followup_count = 0`;
   - `last_interaction_at`.

## Pontos de atencao

- O comando ignora leads com `campaign_id`, portanto campanhas em massa nao entram no follow-up organico.
- O cutoff de seguranca desativa leads ativos sem interacao antiga conforme `aria.followup.zombie_cutoff_days`.
- O prompt de follow-up usa historico da conversa quando `conversation_id` existe.
- Templates de prompt do tipo `followup` agora recebem as variaveis `approach`, `tone_by_attempt`, `message_type`, `tone`, `persuasion_intensity` e `custom_instructions`.
- A tabela `followup_messages.tone` grava o tom configurado no envio.

## Checklist de validacao manual

1. Criar ou abrir um agente em `/agentes`.
2. Abrir `Follow-up`.
3. Salvar tipo de mensagem, tom e persuasao.
4. Criar lead qualificado com `followup_status = active`.
5. Rodar `php artisan aria:check-followups`.
6. Confirmar que um `ProcessLeadFollowUpJob` foi despachado.
7. Confirmar envio pela instancia vinculada ao agente.
8. Confirmar registro em `followup_messages`.
9. Simular resposta inbound e confirmar que o lead volta para `followup_status = inactive`.
