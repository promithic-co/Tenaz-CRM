# Plano de Atualizacao da URA Reversa com Twilio

Data da releitura: 2026-05-12

## Objetivo

Atualizar a URA reversa atual para ficar segura para teste real, mais robusta contra retries/webhooks duplicados da Twilio e mais eficiente em campanhas outbound. Este plano foi feito a partir da leitura do codigo atual e dos padroes do Twilio Developer Kit para Programmable Voice, TwiML, outbound calls, webhook architecture e reliability patterns.

Este arquivo e um plano de implementacao. Ele nao altera comportamento por si so.

## Arquitetura atual lida no codigo

Fluxo principal:

1. `VoiceCampaignService::start()` valida campanha em `draft`, exige DTMF configurado, calcula `total_calls` e dispara `DispatchVoiceCampaignJob`.
2. `DispatchVoiceCampaignJob` percorre entradas da lista, cria `VoiceCampaignCall` por contato e agenda `PlaceOutboundCallJob` com delay incremental baseado em `delay_between_calls_ms`.
3. `PlaceOutboundCallJob` usa `Twilio\Rest\Client` com `services.twilio.sid`, `services.twilio.token` e `services.twilio.phone_number`.
4. A chamada Twilio recebe:
   - `url` para `ivr.script`
   - `statusCallback` para `ivr.status`
   - `statusCallbackEvent` com `completed`, `busy`, `no-answer`, `failed`
5. `IvrController::script()` marca a chamada como `answered`, incrementa `total_answered` e retorna TwiML com `<Gather numDigits="1">`.
6. `IvrController::handleDtmf()` recebe `Digits`, muda status para `interested`, `optout`, `callback` ou `no_interest`, e encerra com `<Hangup>`.
7. `IvrController::statusCallback()` recebe status final. Se for `completed` e a chamada estiver `interested`, dispara `SendPostCallWhatsAppJob`.
8. `SendPostCallWhatsAppJob` cria/reusa `Lead` e envia WhatsApp pela instancia vinculada a `VoiceInstance`.

Arquivos centrais:

- `routes/api.php`
- `app/Http/Controllers/IvrController.php`
- `app/Jobs/DispatchVoiceCampaignJob.php`
- `app/Jobs/PlaceOutboundCallJob.php`
- `app/Jobs/SendPostCallWhatsAppJob.php`
- `app/Services/VoiceCampaignService.php`
- `app/Models/VoiceCampaign.php`
- `app/Models/VoiceCampaignCall.php`
- `app/Models/VoiceInstance.php`
- `app/Http/Middleware/ValidateTwilioSignature.php`
- `tests/Feature/IvrControllerTest.php`
- `tests/Feature/PlaceOutboundCallJobTest.php`
- `tests/Feature/VoiceCampaignTest.php`
- `tests/Feature/SendPostCallWhatsAppJobTest.php`

## Diagnostico

O desenho atual e um MVP correto: chamada outbound, TwiML dinamico, DTMF e ponte para WhatsApp. O ponto fraco nao e a ideia da arquitetura, e sim a falta de garantias operacionais que aparecem quando a Twilio entra em ambiente real.

Principais riscos identificados:

- Webhooks da Twilio podem ser reenviados em timeout/erro; os contadores atuais podem duplicar.
- `statusCallback` atualiza por ID da rota, mas nao valida se `CallSid` recebido bate com `voice_campaign_calls.call_sid`.
- O receiver de status faz regra de negocio direto no request. Para volume, o padrao Twilio recomendado e receiver fino: validar, persistir/enfileirar, responder rapido.
- `VoiceCampaignCall` guarda poucos dados Twilio. Falta `twilio_status`, `answered_by`, `duration_seconds`, `error_code`, `error_message`, `price`, `price_unit`, payload resumido e timestamps por evento.
- Nao ha Answering Machine Detection. Caixa postal e fax podem inflar atendimento e gastar fluxo inutilmente.
- Nao ha idempotency key para callbacks. A Twilio pode enviar `I-Twilio-Idempotency-Token` em retries.
- Cadencia e janela operacional ainda sao simples. O delay existe, mas nao ha controle de CPS/concurrency, horario permitido, limite de tentativas ou retry comercial.
- Opt-out precisa ser tratado como regra forte em dispatch, nao apenas como efeito de uma chamada.
- Teste real ainda depende de campanha completa; falta um modo seguro de chamada de teste.

## Conhecimento Twilio aplicado

Regras relevantes do Twilio Developer Kit:

- Webhooks de voz devem responder em ate 15 segundos.
- TwiML deve sair com `Content-Type: text/xml`.
- Status callbacks nao esperam TwiML; devem retornar `200` ou `204` rapidamente.
- Todo webhook publico deve validar `X-Twilio-Signature`.
- Callbacks nao tem garantia de ordem nem de entrega unica; tratar eventos como idempotentes.
- Para volume, usar receiver fino e processar status de forma assincrona.
- Para outbound campaigns, considerar CPS/concurrent call limits e backoff em erros Twilio.
- AMD (`machineDetection`) ajuda a separar humano, maquina, fax e desconhecido, mas tem custo e acuracia imperfeita.
- AMD async (`asyncAmd`) reduz bloqueio inicial, mas o resultado pode chegar depois do inicio do prompt.
- `CallSid` deve ser a chave operacional de correlacao.

## Plano recomendado

### Fase 0 - Hotfixes antes do primeiro teste real

Objetivo: evitar dados falsos, chamadas indevidas e efeitos duplicados.

Alteracoes:

1. Tornar `IvrController::script()` idempotente.
   - So marcar `answered_at` e incrementar `total_answered` se `answered_at` ainda for `null`.

2. Tornar `IvrController::handleDtmf()` idempotente.
   - So incrementar `total_interested` se a chamada ainda nao estiver `interested`.
   - Para opt-out, garantir que repetir callback nao quebre nem gere efeitos duplicados.

3. Tornar `IvrController::statusCallback()` idempotente.
   - So incrementar `total_no_answer` e `total_failed` uma vez.
   - So disparar `SendPostCallWhatsAppJob` uma vez para a mesma chamada completada/interessada.

4. Validar `CallSid` no `statusCallback`.
   - Se `call_sid` salvo existir e o payload trouxer outro `CallSid`, logar warning e retornar `204` sem alterar a chamada.

5. Filtrar opt-out no dispatch.
   - `DispatchVoiceCampaignJob` e `VoiceCampaignService::start()` devem ignorar contatos com opt-out.

6. Corrigir contagem de atendidas.
   - Contar atendidas por `answered_at IS NOT NULL`, nao por `status = answered`, porque o status e sobrescrito por DTMF.

Testes minimos:

- `script is idempotent and does not double total_answered`
- `dtmf interested is idempotent`
- `status callback no-answer is idempotent`
- `status callback completed interested dispatches whatsapp once`
- `status callback ignores mismatched CallSid`
- `dispatch skips opted_out contacts`
- `answered count uses answered_at`

Arquivos provaveis:

- `app/Http/Controllers/IvrController.php`
- `app/Jobs/DispatchVoiceCampaignJob.php`
- `app/Services/VoiceCampaignService.php`
- `app/Models/VoiceCampaignCall.php`
- `tests/Feature/IvrControllerTest.php`
- `tests/Feature/VoiceCampaignTest.php`

### Fase 1 - Observabilidade e persistencia Twilio

Objetivo: conseguir diagnosticar o primeiro teste real sem depender so de log.

Adicionar colunas em `voice_campaign_calls`:

- `twilio_status`
- `answered_by`
- `duration_seconds`
- `error_code`
- `error_message`
- `sip_response_code`
- `price`
- `price_unit`
- `dtmf_digits`
- `status_callback_payload`
- `last_status_callback_at`
- `twilio_idempotency_token`

Alteracoes:

1. Salvar dados relevantes do `statusCallback`.
2. Salvar `Digits` em `dtmf_digits`.
3. Padronizar logs com `voice_campaign_id`, `voice_campaign_call_id`, `call_sid`, `phone`, `twilio_status`.
4. Criar um pequeno objeto/service para normalizar payload Twilio antes de gravar.
5. Atualizar factories e casts do model.

Testes minimos:

- Status callback persiste `CallStatus`, `CallDuration`, `ErrorCode`, `ErrorMessage`.
- DTMF persiste o digito recebido.
- Campos JSON/casts funcionam em factory.

### Fase 2 - Receiver fino para callbacks

Objetivo: reduzir risco de timeout e duplicidade sob volume.

Desenho:

1. `IvrController::statusCallback()` valida assinatura via middleware, valida correlacao basica e cria um evento/job.
2. Controller responde `204` rapido.
3. Novo job processa o status de forma idempotente.

Opcoes de implementacao:

- Simples: criar `ProcessTwilioCallStatusJob` recebendo `voice_campaign_call_id`, payload e idempotency token.
- Mais auditavel: criar tabela `voice_call_events` com chave unica por `call_sid + event_type + idempotency_token/status`.

Recomendacao pragmatica:

- Comecar com `ProcessTwilioCallStatusJob` + colunas em `voice_campaign_calls`.
- Criar tabela de eventos so se o volume real ou auditoria pedir historico completo.

Testes minimos:

- Controller enfileira job e responde `204`.
- Job processa `no-answer`, `failed`, `busy`, `completed interested`.
- Job ignora evento duplicado.
- Job ignora `CallSid` divergente.

### Fase 3 - AMD para eficiencia

Objetivo: reduzir desperdicio com caixa postal/fax e melhorar qualidade do funil.

Alteracoes em `PlaceOutboundCallJob`:

- Adicionar configuracao por campanha ou global para AMD.
- Enviar parametros Twilio:
  - `machineDetection` = `Enable`
  - `asyncAmd` = `true`
  - `asyncAmdStatusCallback` = rota nova
  - `asyncAmdStatusCallbackMethod` = `POST`

Nova rota:

- `POST /api/ivr/call/{voiceCampaignCall}/amd`

Comportamento recomendado:

- `human`: manter fluxo normal.
- `machine_start`, `machine_end_beep`, `machine_end_silence`: marcar como `voicemail`/`machine`, bloquear WhatsApp pos-call.
- `fax`: marcar como `fax`.
- `unknown`: manter como humano no MVP, mas registrar para metrica.

Colunas/counters:

- `voice_campaign_calls.answered_by`
- `voice_campaign_calls.amd_callback_received_at`
- `voice_campaigns.total_machine`
- `voice_campaigns.total_voicemail`
- `voice_campaigns.total_fax`
- `voice_campaigns.total_unknown`

Observacao: AMD async pode chegar depois do prompt inicial. Portanto, no MVP, nao bloquear o script inicial; apenas corrigir classificacao e impedir pos-call quando o resultado final indicar maquina/fax.

Testes minimos:

- `PlaceOutboundCallJob` envia parametros AMD quando habilitado.
- AMD `human` nao bloqueia fluxo.
- AMD `machine_*` marca voicemail/machine e bloqueia post-call.
- AMD `fax` marca fax.
- Callback AMD duplicado e idempotente.

### Fase 4 - Modo de teste e go-live controlado

Objetivo: testar sem criar campanha real completa nem contaminar metricas de producao.

Alteracoes:

1. Adicionar `is_test` e `test_payload` em `voice_campaign_calls`.
2. Criar endpoint interno para chamada de teste a partir de uma campanha/instancia.
3. `PlaceOutboundCallJob` deve permitir chamada teste mesmo se campanha nao estiver `sending`.
4. Metricas e contadores de campanha devem excluir `is_test = true`.
5. UI de campanha/voz pode exibir botao "Chamada de teste".

Testes minimos:

- Chamada teste cria `VoiceCampaignCall` com `is_test`.
- Chamada teste ignora status da campanha.
- Contadores de campanha excluem chamadas teste.

### Fase 5 - Cadencia, janela e limites

Objetivo: evitar abuso operacional e respeitar limites Twilio/compliance.

Alteracoes:

1. Configurar janela de envio por campanha:
   - `send_hour_start`
   - `send_hour_end`
   - `timezone`
   - `allowed_days`

2. Configurar tentativas:
   - `max_attempts`
   - `attempt_number`
   - `next_attempt_at`

3. Controlar concorrencia/CPS:
   - limite global por worker/fila
   - limite por campanha
   - backoff com jitter em erro 429 da Twilio

4. Reagendar `no-answer` dentro de `max_attempts`, respeitando janela.

Testes minimos:

- Fora da janela, chamada e reagendada.
- Dentro da janela, chamada segue.
- `max_attempts` limita retry.
- Erro 429 reagenda com backoff.

### Fase 6 - Reconciliacao e custo

Objetivo: corrigir chamadas presas e medir custo real.

Alteracoes:

1. Criar command/job `ReconcileStuckVoiceCallsJob`.
   - Busca chamadas `calling` antigas.
   - Consulta Twilio pelo `call_sid`.
   - Atualiza status final quando aplicavel.

2. Criar job `FetchTwilioCallPriceJob`.
   - Executa alguns minutos apos `completed`.
   - Consulta recurso `Call`.
   - Salva `price` e `price_unit`.

3. Exibir custo agregado na pagina de campanha.

Testes minimos:

- Reconciliacao atualiza chamada presa.
- Fetch de preco salva valor e unidade.
- Falhas Twilio sao logadas sem quebrar campanha.

## Ordem de execucao recomendada

1. Fase 0: hotfixes de idempotencia, CallSid, opt-out e contagem.
2. Fase 1: persistencia de payloads Twilio e observabilidade.
3. Fase 4: modo de teste.
4. Primeiro teste real pequeno: 1 a 5 numeros internos.
5. Fase 3: AMD, se o teste mostrar caixa postal/fax relevante.
6. Fase 5: janela, retry e limites antes de volume externo.
7. Fase 6: reconciliacao e custo antes de campanha grande.

## Checklist antes do primeiro teste real

- `APP_URL` publico em HTTPS.
- `TWILIO_ACCOUNT_SID` configurado.
- `TWILIO_AUTH_TOKEN` configurado.
- `TWILIO_PHONE_NUMBER` configurado.
- Fila `campaigns` rodando.
- Fila `messages` rodando.
- `ValidateTwilioSignature` validando em ambiente nao-testing.
- Chamada teste funcionando.
- Opt-out testado.
- DTMF `interested` testado.
- DTMF `optout` testado.
- WhatsApp pos-call testado.
- Logs pesquisaveis por `call_sid`.

## Comandos de verificacao por fase

Usar testes focados:

```bash
php artisan test --compact --filter=IvrControllerTest
php artisan test --compact --filter=VoiceCampaignTest
php artisan test --compact --filter=PlaceOutboundCallJobTest
php artisan test --compact --filter=SendPostCallWhatsAppJobTest
```

Antes de finalizar qualquer fase com codigo PHP:

```bash
vendor/bin/pint --dirty --format agent
```

## Proxima acao sugerida

Implementar a Fase 0 primeiro. Ela e pequena, reduz risco imediato e cria uma base confiavel para testar com telefone real sem inflar metricas nem disparar WhatsApp duplicado.
