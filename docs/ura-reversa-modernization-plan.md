# Plano de Evolucao da URA Reversa

FASES CONCLUIDAS: -
FASE ATUAL (em andamento?): 0.1 (n)
PROXIMA FASE: 0.1

Ultima atualizacao: documento criado. Nenhuma fase iniciada.

---

## Como Usar Este Documento

Este plano e a fonte unica de verdade para evolucao da URA reversa. A IA implementadora deve:

1. **Sempre comecar lendo o cabecalho** acima para identificar a proxima fase a executar.
2. **Executar uma fase por vez**, na ordem listada. Nao pular.
3. **Atualizar o cabecalho ao concluir** uma fase: mover de PROXIMA FASE para FASES CONCLUIDAS, atualizar PROXIMA FASE para a fase seguinte na sequencia, e atualizar a linha "Ultima atualizacao".
4. **Ao concluir uma fase**, criar um arquivo resumo em `docs/ura-modernization-fase-X.Y.md` com: o que foi mudado, arquivos tocados, decisoes tomadas em runtime, problemas encontrados.
5. **Nao tomar decisoes de produto** sem consultar a secao `Decisoes Ja Tomadas` abaixo. Se algo nao estiver coberto la, parar e perguntar.
6. **Manter o estilo do documento**: portugues sem acentos (ex: "evolucao", nao "evolução").
7. **Cada fase tem passos numerados, arquivos exatos e SQL de migration**. Seguir literalmente. Variacoes minimas (nome de variavel, ordem de imports) sao aceitaveis; mudancas estruturais nao.
8. **Antes de codar**, ler os arquivos referenciados na fase para confirmar que nada mudou desde o plano.
9. **Cada fase termina com testes passando** (`php artisan test --compact --filter=...`). Sem testes verdes, fase nao esta concluida.
10. **Pint obrigatorio** antes do commit final da fase: `vendor/bin/pint --dirty --format agent`.

---

## Decisoes Ja Tomadas (nao reabrir)

Estas decisoes foram debatidas e fechadas. A IA implementadora nao deve reabrir nem propor alternativas durante a execucao.

| Tema | Decisao |
|---|---|
| **Idempotencia de webhook** | Guards via timestamps existentes nas colunas (`answered_at IS NULL`, `completed_at IS NULL`). Nao criar tabela `voice_call_events`. |
| **AMD** | Opcao B: prompt curto roda normalmente; AMD callback async; se `answered_by` indicar maquina/fax, marcar a chamada retroativamente como `voicemail`/`fax`, decrementar `total_answered`, incrementar `total_machine`, e bloquear `SendPostCallWhatsAppJob`. |
| **Modo AMD** | `MachineDetection = Enable` com `AsyncAmd = true`. Nao usar `DetectMessageEnd` no MVP. |
| **Custos** | Salvar `price` e `price_unit` em `voice_campaign_calls`. Sem tabela dedicada. Sem dashboard de custos por tenant nesta iteracao. |
| **Gravacao de chamadas** | Fora de escopo. Nao implementar. |
| **Multi-numero Twilio por tenant** | Fora de escopo. Manter `services.twilio.phone_number` global por enquanto. |
| **Tipo de TTS default** | Manter `Google.pt-BR-Standard-A`. Nao trocar para Polly. |
| **Idioma** | Apenas `pt-BR` no MVP. |
| **Timeline de Lead** | Nao existe hoje. Nao criar. Usar colunas `source_channel` + `source_reference_id` em `leads`. |
| **Estilo do documento** | Portugues sem acentos. |
| **Estado pos-DTMF/speech** | Sobrescrever `status` (e.g. `interested`). `answered_at` permanece como fonte de verdade do "atendido". |

---

## Estado Atual do Sistema (verificado em codigo)

Antes de comecar, leia esses arquivos para entender o ponto de partida:

- `app/Services/VoiceCampaignService.php`
- `app/Jobs/DispatchVoiceCampaignJob.php`
- `app/Jobs/PlaceOutboundCallJob.php`
- `app/Http/Controllers/IvrController.php`
- `app/Jobs/SendPostCallWhatsAppJob.php`
- `app/Models/VoiceCampaign.php`
- `app/Models/VoiceCampaignCall.php`
- `app/Models/VoiceInstance.php`
- `app/Http/Middleware/ValidateTwilioSignature.php`
- `app/Http/Controllers/VoiceCampaignController.php`
- `routes/api.php` (rotas IVR)
- `routes/web.php` (rotas campanhas-voz)
- `database/migrations/2026_04_10_121349_create_voice_campaigns_table.php`
- `database/migrations/2026_04_10_121350_create_voice_campaign_calls_table.php`
- `resources/js/pages/campanhas-voz/Show.vue`
- `resources/js/pages/voz/Index.vue`

Fluxo atual (resumido):

1. Usuario cria `VoiceInstance` (`/voz`) e `VoiceCampaign` (`/campanhas-voz`).
2. `VoiceCampaignService::start` valida e dispara `DispatchVoiceCampaignJob`.
3. `DispatchVoiceCampaignJob` cria `VoiceCampaignCall` por entry e dispara `PlaceOutboundCallJob` com delay incremental.
4. `PlaceOutboundCallJob` chama Twilio com `url`, `statusCallback`, `statusCallbackEvent=['completed','busy','no-answer','failed']`. Sem AMD.
5. Twilio chama `IvrController::script` quando atende. Marca `status=answered`, incrementa `total_answered`, retorna TwiML com `<Gather numDigits=1>`.
6. Twilio chama `IvrController::handleDtmf` apos digito. Sobrescreve `status` para `interested|optout|callback|no_interest`.
7. Twilio chama `IvrController::statusCallback` ao final. Se `CallStatus=completed` e `status=interested`, dispara `SendPostCallWhatsAppJob`.
8. `SendPostCallWhatsAppJob` cria/reusa `Lead`, envia WhatsApp.

Problemas confirmados em codigo:

- `DispatchVoiceCampaignJob.php:42` puxa entries sem filtrar `opt_in_status`. Quem deu opt-out continua sendo ligado.
- `IvrController.php:24` incrementa `total_answered` sem guard. Twilio retry pode duplicar.
- `IvrController.php:148-176` incrementa `total_no_answer`/`total_failed` sem guard.
- `VoiceCampaignController.php:22-24` conta `answered_calls_count` por `where('status','answered')` mas `handleDtmf` sobrescreve esse status. Subconta atendidas por linha.
- `voice_campaign_calls` nao tem `twilio_status`, `answered_by`, `duration_seconds`, `sip_response_code`, `error_code`, `price`, `price_unit`, `dtmf_digits`, `speech_result`, `speech_confidence`, `status_callback_payload`, `is_test`, `gather_attempts`, `amd_callback_received_at`.
- `voice_campaigns` nao tem `machine_detection_enabled`, `total_machine`, `total_voicemail`, `total_fax`, `send_hour_start`, `send_hour_end`, `allowed_days`, `max_attempts`.
- `leads` nao tem `source_channel`, `source_reference_id`.
- Sem reconciliacao de chamadas em `calling` que nunca recebem callback.
- Sem modo de teste.
- Sem validador de configuracao Twilio.
- `IvrController::script` nao usa `<Gather input="dtmf speech">`.

---

## Principios

- A chamada deve ser curta. Voz captura interesse, WhatsApp qualifica e vende.
- Cada chamada gera dados operacionais claros: atendeu, era humano, demonstrou interesse, pediu retorno, recusou, opt-out, caiu em caixa postal.
- Toda mudanca preserva o MVP atual e adiciona capacidade em camadas.
- Tudo que dispara chamada real precisa ter modo de teste.
- Opt-out e tratado com rigor desde o inicio.
- Webhook idempotente por padrao.
- Sem decisao de produto durante implementacao. Tudo prescrito ou consultar `Decisoes Ja Tomadas`.

---

# FASE 0: Hotfixes Criticos

Objetivo: corrigir bugs com risco regulatorio ou de integridade de dados antes de qualquer melhoria. Sao mudancas pequenas, com alto impacto.

## Fase 0.1 — Filtrar opt-out no dispatch

Estado: Nao iniciada.

### Por que

`DispatchVoiceCampaignJob` puxa todas as entries da `ContactList`, incluindo as marcadas com `opt_in_status = opted_out`. Quem clicou em "nao quero mais ligacoes" pode receber ligacao da proxima campanha. Risco regulatorio aberto hoje. **Hotfix urgente**.

### Pre-requisitos

Nenhum.

### Passos

1. Editar `app/Jobs/DispatchVoiceCampaignJob.php`:
   - Linha 42, trocar:
     ```php
     $entryIds = $campaign->contactList->entries()
         ->pluck('id')
         ->all();
     ```
     Por:
     ```php
     $entryIds = $campaign->contactList->entries()
         ->where('opt_in_status', '!=', 'opted_out')
         ->pluck('id')
         ->all();
     ```

2. Editar `app/Services/VoiceCampaignService.php`:
   - Linha 24, trocar:
     ```php
     $totalCalls = $campaign->contactList->entries()->count();
     ```
     Por:
     ```php
     $totalCalls = $campaign->contactList->entries()
         ->where('opt_in_status', '!=', 'opted_out')
         ->count();
     ```

3. Adicionar log de auditoria em `DispatchVoiceCampaignJob::handle` apos `$entryIds = ...`:
   ```php
   $totalEntries = $campaign->contactList->entries()->count();
   $excluded = $totalEntries - count($entryIds);
   if ($excluded > 0) {
       Log::info('DispatchVoiceCampaignJob: skipped opted_out entries', [
           'campaign_id' => $campaign->id,
           'excluded' => $excluded,
       ]);
   }
   ```

### Testes

Adicionar em `tests/Feature/VoiceCampaignTest.php`:

- `it skips opted_out entries on dispatch` — cria 3 entries, marca 1 como `opted_out`, dispara `DispatchVoiceCampaignJob`, espera que apenas 2 `VoiceCampaignCall` sejam criadas.
- `start sets total_calls excluding opted_out` — cria 3 entries com 1 opted_out, chama `VoiceCampaignService::start`, espera `voice_campaign.total_calls = 2`.

Comando: `php artisan test --compact --filter=VoiceCampaignTest`.

### Aceite

- [ ] Teste novo passa.
- [ ] Testes pre-existentes continuam passando.
- [ ] Log mostra contador de entries puladas quando aplicavel.

---

## Fase 0.2 — Idempotencia dos webhooks IVR

Estado: Nao iniciada.

### Por que

Twilio pode reenviar callbacks (`statusCallback`, `script`) em retry quando recebe 5xx ou timeout. Hoje `IvrController` incrementa contadores agregados (`total_answered`, `total_no_answer`, `total_failed`) sem guard. Em caso de retry, contadores duplicam. `total_interested` tambem incrementa sem guard via `handleDtmf`.

### Pre-requisitos

Fase 0.1 concluida.

### Passos

1. Editar `app/Http/Controllers/IvrController.php::script` (linhas 17-24):
   - Substituir o bloco inicial por:
     ```php
     if ($voiceCampaignCall->answered_at === null) {
         $voiceCampaignCall->update([
             'status' => 'answered',
             'answered_at' => now(),
         ]);
         $voiceCampaignCall->voiceCampaign()->increment('total_answered');
     }
     ```

2. Editar `app/Http/Controllers/IvrController.php::handleInterested` (linhas 99-108):
   - Substituir corpo por:
     ```php
     if ($call->status !== 'interested') {
         $call->update(['status' => 'interested']);
         $call->voiceCampaign()->increment('total_interested');
     }
     $twiml->say('Perfeito! Em instantes voce recebera uma mensagem no WhatsApp. Ate logo!', [
         'language' => 'pt-BR',
         'voice' => $voice,
     ]);
     ```

3. Editar `app/Http/Controllers/IvrController.php::statusCallback` (linhas 148-184):
   - Adicionar guard no inicio do metodo, depois de `$callStatus = $request->input('CallStatus');`:
     ```php
     $alreadyCompleted = $voiceCampaignCall->completed_at !== null;
     ```
   - Mover `$voiceCampaignCall->update(['completed_at' => now()]);` para apos os incrementos, dentro de `if (! $alreadyCompleted)`.
   - Envolver os incrementos `total_no_answer` e `total_failed` em `if (! $alreadyCompleted)`.
   - Manter o dispatch de `SendPostCallWhatsAppJob` fora do guard (job ja tem retry com `tries=3` e e idempotente em `Lead::firstOrCreate` com lock).

   Resultado esperado do bloco principal:
   ```php
   $callStatus = $request->input('CallStatus');
   $alreadyCompleted = $voiceCampaignCall->completed_at !== null;

   if (! $alreadyCompleted) {
       if ($callStatus === 'no-answer') {
           $voiceCampaignCall->update(['status' => 'no_answer']);
           $voiceCampaignCall->voiceCampaign()->increment('total_no_answer');
       } elseif (in_array($callStatus, ['busy', 'failed', 'canceled'])) {
           $voiceCampaignCall->update(['status' => $callStatus]);
           $voiceCampaignCall->voiceCampaign()->increment('total_failed');
       }
       $voiceCampaignCall->update(['completed_at' => now()]);
   }

   if ($callStatus === 'completed' && $voiceCampaignCall->status === 'interested' && ! $alreadyCompleted) {
       SendPostCallWhatsAppJob::dispatch($voiceCampaignCall->id);
   }
   ```

4. Editar `app/Jobs/PlaceOutboundCallJob.php::failed`:
   - Adicionar guard antes de incrementar `total_failed`:
     ```php
     if ($call->status !== 'failed') {
         $call->update(['status' => 'failed']);
         $call->voiceCampaign()->increment('total_failed');
     }
     ```

### Testes

Adicionar em `tests/Feature/IvrControllerTest.php`:

- `script is idempotent — second call does not double total_answered`.
- `statusCallback completed received twice does not double dispatch`.
- `statusCallback no-answer received twice does not double total_no_answer`.
- `handleDtmf interested called twice does not double total_interested`.

Comando: `php artisan test --compact --filter=IvrControllerTest`.

### Aceite

- [ ] Cada teste de idempotencia passa.
- [ ] Testes pre-existentes continuam passando.
- [ ] `total_answered`, `total_interested`, `total_no_answer`, `total_failed` nao aumentam em segunda entrega do mesmo evento.

---

## Fase 0.3 — Corrigir contagem de "atendidas" no index

Estado: Nao iniciada.

### Por que

`VoiceCampaignController::index` (linhas 22-27) conta atendidas com `where('status', 'answered')`. Como `handleDtmf` sobrescreve `status` para `interested|optout|callback|no_interest`, chamadas que tiveram interacao somem do contador `answered_calls_count` na linha individual da campanha. A soma agregada `total_answered` esta correta, mas a tela de listagem mostra valor inferior ao real.

Causa raiz: a fonte de verdade do "atendido" deve ser `answered_at`, nao `status`.

### Pre-requisitos

Fase 0.2 concluida.

### Passos

1. Editar `app/Http/Controllers/VoiceCampaignController.php` (linhas 22-27):
   - Substituir:
     ```php
     ->withCount(['calls as answered_calls_count' => function ($query) {
         $query->where('status', 'answered');
     }])
     ->withCount(['calls as interested_calls_count' => function ($query) {
         $query->where('status', 'interested');
     }])
     ```
     Por:
     ```php
     ->withCount(['calls as answered_calls_count' => function ($query) {
         $query->whereNotNull('answered_at');
     }])
     ->withCount(['calls as interested_calls_count' => function ($query) {
         $query->where('status', 'interested');
     }])
     ```

2. Auditar uso de `VoiceCampaignCall::scopeAnswered`:
   - Em `app/Models/VoiceCampaignCall.php` (linhas 57-60), trocar:
     ```php
     public function scopeAnswered(Builder $query): Builder
     {
         return $query->where('status', 'answered');
     }
     ```
     Por:
     ```php
     public function scopeAnswered(Builder $query): Builder
     {
         return $query->whereNotNull('answered_at');
     }
     ```

3. Verificar se ha outros pontos no codigo que usam `status = answered` para contar atendidas. Buscar com `grep -rn "status.*answered" app/ resources/`. Se encontrar, corrigir caso a caso. Documentar achados no arquivo `docs/ura-modernization-fase-0.3.md`.

### Testes

Adicionar em `tests/Feature/Controllers/VoiceCampaignControllerTest.php` (criar se nao existir):

- `index counts answered calls by answered_at not status` — cria campanha com 3 calls: 1 com `answered_at` setado e `status=answered`, 1 com `answered_at` setado e `status=interested`, 1 sem `answered_at`. Espera `answered_calls_count = 2`.

### Aceite

- [ ] Teste novo passa.
- [ ] Testes pre-existentes continuam passando.
- [ ] Tela `/campanhas-voz` mostra contagem coerente com `total_answered` agregado.

---

# FASE 1: Confiabilidade MVP

Objetivo: tornar a URA atual confiavel, mensuravel e segura para testes reais controlados, antes de adicionar speech ou AMD.

## Fase 1.1 — Modo de teste de chamada

Estado: Nao iniciada.

### Por que

Sem modo de teste, qualquer iteracao em script, voz, DTMF ou WhatsApp pos-chamada exige montar uma `ContactList` minima, configurar tudo e dar start. Isso atrasa todas as fases seguintes.

### Pre-requisitos

Fase 0.3 concluida.

### Passos

1. Migration nova: `php artisan make:migration add_test_mode_to_voice_campaign_calls --no-interaction`
   ```php
   Schema::table('voice_campaign_calls', function (Blueprint $table) {
       $table->boolean('is_test')->default(false)->after('completed_at');
       $table->json('test_payload')->nullable()->after('is_test');
       $table->index(['voice_campaign_id', 'is_test']);
   });
   ```

2. Editar `app/Models/VoiceCampaignCall.php`:
   - Adicionar `is_test` e `test_payload` em `$fillable`.
   - Adicionar cast `'is_test' => 'boolean', 'test_payload' => 'array'`.
   - Adicionar scope:
     ```php
     public function scopeProduction(Builder $query): Builder
     {
         return $query->where('is_test', false);
     }
     ```

3. Form Request: `php artisan make:request StoreVoiceTestCallRequest --no-interaction`
   - Regras:
     ```php
     return [
         'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
         'name' => ['nullable', 'string', 'max:120'],
         'extra_data' => ['nullable', 'array'],
     ];
     ```
   - `authorize`: retornar `true` (autorizacao via policy do controller).

4. Editar `app/Http/Controllers/VoiceCampaignController.php`:
   - Adicionar metodo `testCall`:
     ```php
     public function testCall(VoiceCampaign $voiceCampaign, StoreVoiceTestCallRequest $request, VoiceCampaignService $service): RedirectResponse
     {
         $this->authorize('update', $voiceCampaign);

         try {
             $service->placeTestCall($voiceCampaign, $request->validated());
         } catch (\RuntimeException $e) {
             return back()->withErrors(['test_call' => $e->getMessage()]);
         }

         return back()->with('success', 'Chamada de teste disparada.');
     }
     ```

5. Editar `app/Services/VoiceCampaignService.php`:
   - Adicionar metodo `placeTestCall`:
     ```php
     public function placeTestCall(VoiceCampaign $campaign, array $payload): void
     {
         $phone = str_starts_with($payload['phone'], '+') ? $payload['phone'] : '+' . $payload['phone'];
         $name = $payload['name'] ?? 'Teste';
         $extraData = $payload['extra_data'] ?? [];

         $template = $campaign->greeting_template ?? $campaign->voiceInstance->greeting_template ?? '';
         $vars = array_merge(['nome' => $name], $extraData);
         $message = preg_replace_callback('/\{(\w+)\}/', fn ($m) => $vars[$m[1]] ?? $m[0], $template);

         $call = VoiceCampaignCall::create([
             'voice_campaign_id' => $campaign->id,
             'contact_list_entry_id' => null,
             'phone' => $phone,
             'contact_name' => $name,
             'interpolated_message' => $message,
             'status' => 'pending',
             'is_test' => true,
             'test_payload' => $payload,
         ]);

         PlaceOutboundCallJob::dispatch($call);

         Log::info('VoiceCampaignService.placeTestCall', [
             'campaign_id' => $campaign->id,
             'call_id' => $call->id,
             'phone' => $phone,
         ]);
     }
     ```

6. Ajustar `app/Jobs/PlaceOutboundCallJob.php::handle`:
   - O guard `if (! $voiceCampaign->isSending())` deve permitir `is_test = true`:
     ```php
     if (! $voiceCampaign->isSending() && ! $call->is_test) {
         Log::info('PlaceOutboundCallJob: campaign not sending, aborting', [
             'call_id' => $call->id,
             'campaign_status' => $voiceCampaign->status,
         ]);
         return;
     }
     ```

7. Excluir testes do agregado em `app/Http/Controllers/VoiceCampaignController.php`:
   - No `index`, todos os `withCount` recebem closure que adiciona `->where('is_test', false)`.
   - No `show`, `interestedCalls` e `allCalls` recebem `->where('is_test', false)` (e adicionar uma aba/secao separada para chamadas de teste se quiser visualizar).
   - Em `app/Http/Controllers/IvrController.php::statusCallback`, o calculo de "campanha completa" deve excluir `is_test`:
     ```php
     $pending = VoiceCampaignCall::where('voice_campaign_id', $campaign->id)
         ->where('is_test', false)
         ->whereIn('status', ['pending', 'calling'])
         ->count();
     ```
   - Em `app/Http/Controllers/IvrController.php`, ao incrementar contadores agregados (`total_answered`, etc.) **nao incrementar quando `is_test = true`**. Adicionar guard antes de cada increment:
     ```php
     if (! $voiceCampaignCall->is_test) {
         $voiceCampaignCall->voiceCampaign()->increment('total_answered');
     }
     ```
   - Idem para `total_interested`, `total_no_answer`, `total_failed`.

8. Rota: editar `routes/web.php`, dentro do grupo `campanhas-voz`, adicionar:
   ```php
   Route::post('{voiceCampaign}/test-call', [VoiceCampaignController::class, 'testCall'])->name('test-call');
   ```

9. UI: editar `resources/js/pages/campanhas-voz/Show.vue`:
   - Adicionar botao "Enviar chamada de teste" que abre modal.
   - Modal com campos: telefone, nome (opcional), JSON de extra_data (textarea opcional).
   - Submit via `<Form>` do Inertia para a rota `voiceCampaigns.testCall` (verificar nome real gerado pelo Wayfinder em `resources/js/actions/App/Http/Controllers/VoiceCampaignController.ts`).
   - Apos sucesso, listar chamadas de teste em uma secao separada do componente.

10. Backend para listar chamadas de teste:
    - Em `VoiceCampaignController::show`, adicionar:
      ```php
      'testCalls' => $voiceCampaign->calls()
          ->where('is_test', true)
          ->orderByDesc('created_at')
          ->limit(20)
          ->get(),
      ```

### Testes

Adicionar em `tests/Feature/Controllers/VoiceCampaignControllerTest.php`:

- `testCall creates a VoiceCampaignCall with is_test=true and dispatches PlaceOutboundCallJob`.
- `testCall does not increment total_calls`.
- `cross-tenant testCall is forbidden`.
- `index excludes test calls from answered_calls_count`.
- `statusCallback for a test call does not increment campaign aggregates`.

### Aceite

- [ ] Operador consegue ligar para um numero unico sem iniciar a campanha.
- [ ] Status da chamada de teste aparece em secao separada na tela `Show.vue`.
- [ ] DTMF de interesse em teste dispara WhatsApp pos-chamada normalmente.
- [ ] Metricas principais da campanha (`total_calls`, `total_answered`, etc.) nao sao impactadas.
- [ ] Todos os testes passam.

---

## Fase 1.2 — Validador de configuracao Twilio

Estado: Nao iniciada.

### Por que

Hoje, falha de configuracao (SID, token, numero, APP_URL nao HTTPS) so aparece no log do job. Operador descobre depois de iniciar uma campanha. Validar no momento do start (e exibir status na tela de instancia).

### Pre-requisitos

Fase 1.1 concluida.

### Passos

1. Criar `app/Services/Voice/TwilioConfigValidator.php`:
   ```php
   <?php

   namespace App\Services\Voice;

   class TwilioConfigValidator
   {
       /**
        * @return array{valid: bool, errors: array<int, string>}
        */
       public function validate(): array
       {
           $errors = [];

           if (empty(config('services.twilio.sid'))) {
               $errors[] = 'TWILIO_ACCOUNT_SID nao configurado.';
           }
           if (empty(config('services.twilio.token'))) {
               $errors[] = 'TWILIO_AUTH_TOKEN nao configurado.';
           }
           if (empty(config('services.twilio.phone_number'))) {
               $errors[] = 'TWILIO_PHONE_NUMBER nao configurado.';
           }

           $appUrl = (string) config('app.url');
           if (! app()->environment('local', 'testing')) {
               if (! str_starts_with($appUrl, 'https://')) {
                   $errors[] = 'APP_URL deve ser HTTPS em ambientes nao locais (atual: ' . $appUrl . ').';
               }
           }

           return [
               'valid' => empty($errors),
               'errors' => $errors,
           ];
       }
   }
   ```

2. Editar `app/Services/VoiceCampaignService.php::start`:
   - Apos validar `canStart` e antes de `$totalCalls`, chamar:
     ```php
     $validation = app(\App\Services\Voice\TwilioConfigValidator::class)->validate();
     if (! $validation['valid']) {
         throw new \RuntimeException('Configuracao Twilio invalida: ' . implode(' ', $validation['errors']));
     }
     ```

3. Editar `app/Services/VoiceCampaignService.php::placeTestCall`:
   - Mesma validacao no inicio do metodo.

4. Editar `app/Http/Controllers/VoiceInstanceController.php::index` (assumindo que existe; se nao, criar um endpoint de status simples):
   - Buscar arquivo: `app/Http/Controllers/VoiceInstanceController.php`. Se controller existente puxa instancias, adicionar prop `twilioConfig`:
     ```php
     'twilioConfig' => app(\App\Services\Voice\TwilioConfigValidator::class)->validate(),
     ```

5. UI `resources/js/pages/voz/Index.vue`:
   - Adicionar banner no topo: se `twilioConfig.valid === false`, mostrar bloco vermelho com lista de `errors`. Se valid, mostrar badge verde "Twilio configurado".

### Testes

Criar `tests/Unit/Services/Voice/TwilioConfigValidatorTest.php`:

- `returns valid when all credentials and HTTPS APP_URL set`.
- `returns errors when TWILIO_ACCOUNT_SID missing`.
- `returns errors when TWILIO_AUTH_TOKEN missing`.
- `returns errors when TWILIO_PHONE_NUMBER missing`.
- `returns error when APP_URL is HTTP in production environment`.
- `does not require HTTPS in local environment`.

Adicionar em `tests/Feature/VoiceCampaignTest.php`:

- `start fails with clear error when Twilio config is incomplete`.

Usar `config()->set(...)` e `app()->detectEnvironment(fn () => 'production')` nos testes para forcar cenarios.

### Aceite

- [ ] Sem credenciais, campanha nao inicia. Erro legivel.
- [ ] APP_URL HTTP em producao bloqueia start.
- [ ] Local funciona com APP_URL HTTP.
- [ ] Banner aparece em `/voz`.
- [ ] Todos os testes passam.

---

## Fase 1.3 — Tracking enriquecido de chamada

Estado: Nao iniciada.

### Por que

`voice_campaign_calls` so guarda `status`, `call_sid` e timestamps. Falta `twilio_status`, `answered_by`, duracao, codigo SIP, payload bruto, custo. Sem isso, dashboards e analise das fases 2 e 3 nao tem dado.

### Pre-requisitos

Fase 1.2 concluida.

### Passos

1. Migration: `php artisan make:migration add_tracking_fields_to_voice_campaign_calls --no-interaction`
   ```php
   Schema::table('voice_campaign_calls', function (Blueprint $table) {
       $table->string('twilio_status')->nullable()->after('status');
       $table->string('answered_by')->nullable()->after('twilio_status');
       $table->unsignedInteger('duration_seconds')->nullable()->after('answered_by');
       $table->string('sip_response_code', 8)->nullable()->after('duration_seconds');
       $table->string('error_code', 16)->nullable()->after('sip_response_code');
       $table->text('error_message')->nullable()->after('error_code');
       $table->decimal('price', 10, 5)->nullable()->after('error_message');
       $table->string('price_unit', 8)->nullable()->after('price');
       $table->json('status_callback_payload')->nullable()->after('price_unit');
       $table->timestamp('last_event_at')->nullable()->after('status_callback_payload');
   });
   ```

2. Editar `app/Models/VoiceCampaignCall.php`:
   - Adicionar todos os novos campos em `$fillable`.
   - Casts:
     ```php
     'duration_seconds' => 'integer',
     'price' => 'decimal:5',
     'status_callback_payload' => 'array',
     'last_event_at' => 'datetime',
     ```

3. Editar `app/Jobs/PlaceOutboundCallJob.php::handle`:
   - Mudar `statusCallbackEvent` para incluir todos os eventos relevantes:
     ```php
     'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
     ```
   - Manter o resto identico.

4. Editar `app/Http/Controllers/IvrController.php::statusCallback`:
   - Apos pegar `$callStatus`, ler todos os campos:
     ```php
     $payload = $request->all();
     $updates = [
         'twilio_status' => $callStatus,
         'last_event_at' => now(),
         'status_callback_payload' => $payload,
     ];
     if ($request->filled('CallDuration')) {
         $updates['duration_seconds'] = (int) $request->input('CallDuration');
     }
     if ($request->filled('AnsweredBy')) {
         $updates['answered_by'] = $request->input('AnsweredBy');
     }
     if ($request->filled('SipResponseCode')) {
         $updates['sip_response_code'] = (string) $request->input('SipResponseCode');
     }
     if ($request->filled('ErrorCode')) {
         $updates['error_code'] = (string) $request->input('ErrorCode');
     }
     if ($request->filled('ErrorMessage')) {
         $updates['error_message'] = (string) $request->input('ErrorMessage');
     }
     $voiceCampaignCall->update($updates);
     ```
   - Esse update e feito sempre (idempotente — sobrescrever campos terminais e ok porque a Twilio reenviou os mesmos valores).
   - Manter o guard `$alreadyCompleted` apenas em torno dos incrementos de agregados e do `completed_at`.

5. Reconciliacao de `price` (assincrona, porque Twilio nao envia preco no callback final):
   - Criar job `app/Jobs/FetchTwilioCallPriceJob.php`:
     ```php
     <?php

     namespace App\Jobs;

     use App\Models\VoiceCampaignCall;
     use Illuminate\Contracts\Queue\ShouldQueue;
     use Illuminate\Foundation\Queue\Queueable;
     use Illuminate\Support\Facades\Log;
     use Twilio\Rest\Client;

     class FetchTwilioCallPriceJob implements ShouldQueue
     {
         use Queueable;

         public int $tries = 3;
         public int $timeout = 30;

         public function __construct(public readonly int $voiceCampaignCallId)
         {
             $this->onQueue('campaigns');
         }

         public function handle(): void
         {
             $call = VoiceCampaignCall::find($this->voiceCampaignCallId);
             if (! $call || ! $call->call_sid) {
                 return;
             }

             $client = new Client(config('services.twilio.sid'), config('services.twilio.token'));
             $remote = $client->calls($call->call_sid)->fetch();

             $call->update([
                 'price' => $remote->price !== null ? (float) $remote->price : null,
                 'price_unit' => $remote->priceUnit,
             ]);

             Log::info('FetchTwilioCallPriceJob.fetched', [
                 'call_id' => $call->id,
                 'price' => $call->price,
                 'price_unit' => $call->price_unit,
             ]);
         }
     }
     ```

   - Em `IvrController::statusCallback`, ao final do bloco `! $alreadyCompleted` quando `$callStatus = 'completed'`, agendar o fetch com delay de 5 minutos:
     ```php
     if ($callStatus === 'completed' && ! $alreadyCompleted) {
         FetchTwilioCallPriceJob::dispatch($voiceCampaignCall->id)->delay(now()->addMinutes(5));
     }
     ```

### Testes

Adicionar em `tests/Feature/IvrControllerTest.php`:

- `statusCallback persists CallDuration when present`.
- `statusCallback persists AnsweredBy when present`.
- `statusCallback persists SipResponseCode when present`.
- `statusCallback handles missing ErrorCode and ErrorMessage gracefully`.
- `statusCallback persists raw payload to status_callback_payload`.
- `statusCallback dispatches FetchTwilioCallPriceJob on completed`.

Criar `tests/Feature/Jobs/FetchTwilioCallPriceJobTest.php`:

- `fetches price from Twilio Call resource and updates call` — usar HTTP fake do SDK (mockar `Twilio\Rest\Client`).

### Aceite

- [ ] Cada chamada mostra `twilio_status` cru e status interno.
- [ ] Duracao registrada quando Twilio envia.
- [ ] `SipResponseCode` salvo quando enviado.
- [ ] `ErrorCode` ausente nao quebra processamento.
- [ ] Payload bruto preservado em `status_callback_payload`.
- [ ] Preco populado em ate 5 minutos pos-callback (quando disponivel).
- [ ] Todos os testes passam.

---

## Fase 1.4 — Reconciliacao de chamadas orfas

Estado: Nao iniciada.

### Por que

Se o status callback nunca chega (timeout, queue parada, deploy entre webhook), `VoiceCampaignCall` fica em `calling` para sempre e a campanha nao completa. Precisa de job scheduled que busca calls travadas e consulta a Twilio.

### Pre-requisitos

Fase 1.3 concluida.

### Passos

1. Criar `app/Jobs/ReconcileStuckVoiceCallsJob.php`:
   ```php
   <?php

   namespace App\Jobs;

   use App\Models\VoiceCampaignCall;
   use Illuminate\Contracts\Queue\ShouldQueue;
   use Illuminate\Foundation\Queue\Queueable;
   use Illuminate\Support\Facades\Log;
   use Twilio\Rest\Client;

   class ReconcileStuckVoiceCallsJob implements ShouldQueue
   {
       use Queueable;

       public int $tries = 1;
       public int $timeout = 300;

       public function __construct()
       {
           $this->onQueue('campaigns');
       }

       public function handle(): void
       {
           $cutoff = now()->subMinutes(15);

           $stuck = VoiceCampaignCall::query()
               ->whereIn('status', ['pending', 'calling'])
               ->whereNotNull('call_sid')
               ->where('called_at', '<', $cutoff)
               ->limit(200)
               ->get();

           if ($stuck->isEmpty()) {
               return;
           }

           $client = new Client(config('services.twilio.sid'), config('services.twilio.token'));

           foreach ($stuck as $call) {
               try {
                   $remote = $client->calls($call->call_sid)->fetch();

                   $newStatus = match ($remote->status) {
                       'completed' => $call->status === 'interested' ? 'interested' : 'answered',
                       'no-answer' => 'no_answer',
                       'busy', 'failed', 'canceled' => $remote->status,
                       default => $call->status,
                   };

                   $call->update([
                       'status' => $newStatus,
                       'twilio_status' => $remote->status,
                       'duration_seconds' => $remote->duration !== null ? (int) $remote->duration : $call->duration_seconds,
                       'price' => $remote->price !== null ? (float) $remote->price : $call->price,
                       'price_unit' => $remote->priceUnit ?? $call->price_unit,
                       'completed_at' => $call->completed_at ?? now(),
                       'last_event_at' => now(),
                   ]);

                   Log::info('ReconcileStuckVoiceCallsJob.reconciled', [
                       'call_id' => $call->id,
                       'twilio_status' => $remote->status,
                   ]);
               } catch (\Throwable $e) {
                   Log::warning('ReconcileStuckVoiceCallsJob.fetch_failed', [
                       'call_id' => $call->id,
                       'error' => $e->getMessage(),
                   ]);
               }
           }
       }
   }
   ```

2. Schedule em `routes/console.php`:
   ```php
   Schedule::job(new \App\Jobs\ReconcileStuckVoiceCallsJob)->hourly()->onOneServer()->name('reconcile-stuck-voice-calls')->withoutOverlapping();
   ```

3. Garantir que o scheduler esta rodando (verificar `bootstrap/app.php` ou Horizon supervisor para fila `campaigns`). Se nao estiver, adicionar nota no arquivo de execucao da fase: `docs/ura-modernization-fase-1.4.md`.

### Testes

Criar `tests/Feature/Jobs/ReconcileStuckVoiceCallsJobTest.php`:

- `picks up calls in calling status older than 15 minutes`.
- `does not pick up recent calls`.
- `does not pick up calls without call_sid`.
- `updates call to completed when Twilio reports completed`.
- `updates call to no_answer when Twilio reports no-answer`.
- `preserves interested status when Twilio reports completed and local status is interested`.
- `logs warning and continues when Twilio fetch fails for a single call`.

Mockar `Twilio\Rest\Client` ou usar wrapper de servico.

### Aceite

- [ ] Job roda toda hora sem overlap.
- [ ] Calls em `calling` por mais de 15 min sao reconciliadas.
- [ ] Falha em uma call nao para o job inteiro.
- [ ] Todos os testes passam.

---

# FASE 2: URA Moderna

Objetivo: deixar a URA menos mecanica, aceitando fala, gerando contexto, melhorando handoff para WhatsApp e respeitando janelas operacionais.

## Fase 2.1 — Answering Machine Detection (assincrono, Opcao B)

Estado: Nao iniciada.

### Por que

Caixa postal infla `total_answered` e dispara WhatsApp pos-chamada para quem nao atendeu de verdade. AMD e a unica forma confiavel de separar humano de maquina em outbound.

**Decisao ja tomada (vide `Decisoes Ja Tomadas`)**: Opcao B. O prompt curto roda normalmente; AMD callback chega assincronamente; se `answered_by` for maquina/fax, marcar retroativamente como voicemail/fax, decrementar `total_answered`, incrementar `total_machine`/`total_voicemail`/`total_fax`, e bloquear `SendPostCallWhatsAppJob`.

### Pre-requisitos

Fase 1.4 concluida.

### Passos

1. Migration: `php artisan make:migration add_amd_to_voice_campaigns_and_calls --no-interaction`
   ```php
   Schema::table('voice_campaigns', function (Blueprint $table) {
       $table->boolean('machine_detection_enabled')->default(true)->after('dtmf_actions');
       $table->unsignedInteger('total_machine')->default(0)->after('total_failed');
       $table->unsignedInteger('total_voicemail')->default(0)->after('total_machine');
       $table->unsignedInteger('total_fax')->default(0)->after('total_voicemail');
       $table->unsignedInteger('total_unknown')->default(0)->after('total_fax');
   });

   Schema::table('voice_campaign_calls', function (Blueprint $table) {
       $table->timestamp('amd_callback_received_at')->nullable()->after('last_event_at');
   });
   ```

2. Editar `app/Models/VoiceCampaign.php`:
   - Adicionar campos em `$fillable` e `casts`.
   - Adicionar metodo:
     ```php
     public function machineRate(): float
     {
         if ($this->total_calls <= 0) {
             return 0.0;
         }
         return round((($this->total_machine + $this->total_voicemail + $this->total_fax) / $this->total_calls) * 100, 2);
     }
     ```

3. Editar `app/Jobs/PlaceOutboundCallJob.php::handle`:
   - Apos `$call = $this->voiceCampaignCall->load('voiceCampaign');`, montar parametros:
     ```php
     $params = [
         'url' => route('ivr.script', $call),
         'statusCallback' => route('ivr.status', $call),
         'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
         'statusCallbackMethod' => 'POST',
         'method' => 'POST',
         'timeout' => 30,
     ];

     if ($voiceCampaign->machine_detection_enabled) {
         $params['machineDetection'] = 'Enable';
         $params['asyncAmd'] = 'true';
         $params['asyncAmdStatusCallback'] = route('ivr.amd', $call);
         $params['asyncAmdStatusCallbackMethod'] = 'POST';
     }

     $twilioCall = $client->calls->create(
         $call->phone,
         config('services.twilio.phone_number'),
         $params
     );
     ```

4. Adicionar rota AMD em `routes/api.php` (dentro do grupo `ivr/call` com middleware `twilio.signature`):
   ```php
   Route::post('{voiceCampaignCall}/amd', [IvrController::class, 'amdCallback'])
       ->name('ivr.amd');
   ```

5. Editar `app/Http/Controllers/IvrController.php`, adicionar metodo:
   ```php
   public function amdCallback(VoiceCampaignCall $voiceCampaignCall, Request $request): Response
   {
       if ($voiceCampaignCall->amd_callback_received_at !== null) {
           Log::info('ivr.amd.duplicate_ignored', ['call_id' => $voiceCampaignCall->id]);
           return response('', 204);
       }

       $answeredBy = (string) $request->input('AnsweredBy', 'unknown');
       $voiceCampaignCall->update([
           'answered_by' => $answeredBy,
           'amd_callback_received_at' => now(),
       ]);

       $isMachine = str_starts_with($answeredBy, 'machine_');
       $isFax = $answeredBy === 'fax';
       $isUnknown = $answeredBy === 'unknown';

       if (($isMachine || $isFax) && ! $voiceCampaignCall->is_test) {
           $campaign = $voiceCampaignCall->voiceCampaign;

           // Decrementar total_answered se ja foi incrementado pelo script()
           if ($voiceCampaignCall->answered_at !== null) {
               $campaign->decrement('total_answered');
           }

           if ($isFax) {
               $voiceCampaignCall->update(['status' => 'fax']);
               $campaign->increment('total_fax');
           } else {
               $voiceCampaignCall->update(['status' => 'voicemail']);
               $campaign->increment('total_voicemail');
               $campaign->increment('total_machine');
           }
       } elseif ($isUnknown && ! $voiceCampaignCall->is_test) {
           $voiceCampaignCall->voiceCampaign->increment('total_unknown');
       }

       Log::info('ivr.amd.classified', [
           'call_id' => $voiceCampaignCall->id,
           'answered_by' => $answeredBy,
       ]);

       return response('', 204);
   }
   ```

6. Editar `app/Jobs/SendPostCallWhatsAppJob.php::handle`:
   - Logo apos carregar `$call`, adicionar guard:
     ```php
     if ($call && in_array($call->answered_by, ['machine_start', 'machine_end_beep', 'machine_end_silence', 'machine_end_other', 'fax'], true)) {
         Log::info('SendPostCallWhatsAppJob.skipped_machine', [
             'call_id' => $call->id,
             'answered_by' => $call->answered_by,
         ]);
         return;
     }
     ```
   - Esse guard fica antes da checagem de `$whatsappInstance`.

7. Atualizar UI `resources/js/pages/campanhas-voz/Show.vue` para mostrar contadores: humanos atendidos (`total_answered`), caixa postal (`total_voicemail`), fax (`total_fax`), desconhecido (`total_unknown`).

### Testes

Criar `tests/Feature/IvrAmdCallbackTest.php`:

- `amd callback with AnsweredBy=human stores answered_by and does not change status`.
- `amd callback with AnsweredBy=machine_start marks call as voicemail and decrements total_answered`.
- `amd callback with AnsweredBy=fax marks call as fax`.
- `amd callback with AnsweredBy=unknown increments total_unknown`.
- `amd callback is idempotent — second delivery does not double counters`.
- `amd callback for test call does not affect aggregates`.

Adicionar em `tests/Feature/Jobs/SendPostCallWhatsAppJobTest.php` (criar se nao existir):

- `does not send WhatsApp when answered_by is machine_start`.
- `does not send WhatsApp when answered_by is fax`.
- `sends WhatsApp when answered_by is human`.
- `sends WhatsApp when answered_by is null (AMD disabled)`.

### Aceite

- [ ] Caixa postal nao conta como humano atendido.
- [ ] `answered_by` salvo em todas as chamadas com AMD habilitado.
- [ ] WhatsApp pos-chamada nao dispara para `machine_*` ou `fax`.
- [ ] Contadores `total_machine`, `total_voicemail`, `total_fax`, `total_unknown` populados.
- [ ] Tela mostra separadamente humano, caixa postal, fax e desconhecido.
- [ ] Todos os testes passam.

---

## Fase 2.2 — Gather com fala + DTMF

Estado: Nao iniciada.

### Por que

Muita gente nao pressiona tecla mas responde "sim", "tenho interesse", "agora nao". Aceitar fala alem de DTMF aumenta interesse capturado.

### Pre-requisitos

Fase 2.1 concluida.

### Passos

1. Migration: `php artisan make:migration add_speech_fields_to_voice_campaign_calls --no-interaction`
   ```php
   Schema::table('voice_campaign_calls', function (Blueprint $table) {
       $table->string('dtmf_digits', 16)->nullable()->after('amd_callback_received_at');
       $table->text('speech_result')->nullable()->after('dtmf_digits');
       $table->decimal('speech_confidence', 4, 3)->nullable()->after('speech_result');
   });
   ```

2. Editar `app/Models/VoiceCampaignCall.php`:
   - Adicionar `dtmf_digits`, `speech_result`, `speech_confidence` em `$fillable`.
   - Cast `'speech_confidence' => 'decimal:3'`.

3. Editar `app/Http/Controllers/IvrController.php::script`:
   - Trocar o bloco `$gather = $twiml->gather([...])` por:
     ```php
     $gather = $twiml->gather([
         'input' => 'dtmf speech',
         'language' => 'pt-BR',
         'numDigits' => '1',
         'timeout' => '5',
         'speechTimeout' => 'auto',
         'actionOnEmptyResult' => true,
         'action' => route('ivr.dtmf', $voiceCampaignCall),
         'method' => 'POST',
     ]);
     ```
   - Resto do metodo identico.

4. Renomear `handleDtmf` para `handleGather` em `app/Http/Controllers/IvrController.php`. Atualizar:
   - Nome da rota em `routes/api.php` permanece `ivr.dtmf` (compatibilidade — nao mexer).
   - Apenas o nome do metodo PHP muda.
   - Atualizar referencia em `routes/api.php`:
     ```php
     Route::post('{voiceCampaignCall}/dtmf', [IvrController::class, 'handleGather'])
         ->name('ivr.dtmf');
     ```

5. Editar corpo de `handleGather`:
   ```php
   public function handleGather(VoiceCampaignCall $voiceCampaignCall, Request $request): Response
   {
       $digits = (string) $request->input('Digits', '');
       $speechResult = (string) $request->input('SpeechResult', '');
       $confidence = $request->input('Confidence');

       $voiceCampaignCall->update([
           'dtmf_digits' => $digits !== '' ? $digits : null,
           'speech_result' => $speechResult !== '' ? $speechResult : null,
           'speech_confidence' => $confidence !== null ? (float) $confidence : null,
       ]);

       $campaign = $voiceCampaignCall->voiceCampaign;
       $voice = $campaign->tts_voice ?? 'Google.pt-BR-Standard-A';

       $intent = app(\App\Services\Voice\VoiceIntentResolver::class)
           ->resolve($digits, $speechResult, $confidence !== null ? (float) $confidence : null, $campaign->resolvedDtmfActions());

       Log::info('ivr.gather', [
           'call_id' => $voiceCampaignCall->id,
           'digits' => $digits,
           'speech' => $speechResult,
           'confidence' => $confidence,
           'intent' => $intent->value,
       ]);

       $twiml = new \Twilio\TwiML\VoiceResponse;

       match ($intent) {
           \App\Enums\VoiceIntent::Interested => $this->handleInterested($voiceCampaignCall, $twiml, $voice),
           \App\Enums\VoiceIntent::OptOut => $this->handleOptout($voiceCampaignCall, $twiml, $voice),
           \App\Enums\VoiceIntent::Callback => $this->handleCallback($voiceCampaignCall, $twiml, $voice),
           \App\Enums\VoiceIntent::NoInterest => $this->handleHangup($voiceCampaignCall, $twiml, $voice),
           \App\Enums\VoiceIntent::Unknown => $twiml->say('Nao entendi sua opcao. Ate logo!', ['language' => 'pt-BR', 'voice' => $voice]),
       };

       $twiml->hangup();

       return response($twiml->asXML(), 200)->header('Content-Type', 'text/xml');
   }
   ```

   `VoiceIntentResolver` e o enum sao criados na Fase 2.3. Esta fase 2.2 deve ser implementada **junto** com a 2.3, ou a 2.3 antes — recomendado executar 2.3 primeiro e depois voltar para 2.2.

   **Reordenacao**: executar 2.3 antes de 2.2. Atualizar cabecalho com a ordem correta na execucao real.

### Testes

Adicionar em `tests/Feature/IvrControllerTest.php`:

- `gather with Digits=1 sets intent to Interested`.
- `gather with SpeechResult="tenho interesse" sets intent to Interested`.
- `gather with SpeechResult="nao quero" sets intent to OptOut`.
- `gather with empty Digits and SpeechResult triggers Unknown intent`.
- `gather with low Confidence does not auto-set Interested` (e.g., Confidence=0.2 e SpeechResult ambiguo).
- `gather without Confidence header does not break`.
- `dtmf_digits and speech_result are persisted`.

### Aceite

- [ ] Cliente pode pressionar 1 ou falar "tenho interesse".
- [ ] "nao quero" vira opt-out.
- [ ] Confianca baixa nao gera interesse automaticamente.
- [ ] Texto reconhecido persistido.
- [ ] `Confidence` ausente nao gera erro.
- [ ] Todos os testes passam.

---

## Fase 2.3 — VoiceIntent enum + resolver

Estado: Nao iniciada.

### Por que

DTMF e fala precisam convergir para a mesma semantica interna. Sem isso, `IvrController` cresceria com logica duplicada.

### Pre-requisitos

Fase 2.1 concluida. (Executar **antes** de 2.2.)

### Passos

1. Criar enum `app/Enums/VoiceIntent.php`:
   ```php
   <?php

   namespace App\Enums;

   enum VoiceIntent: string
   {
       case Interested = 'interested';
       case OptOut = 'optout';
       case Callback = 'callback';
       case NoInterest = 'no_interest';
       case Unknown = 'unknown';
   }
   ```

2. Criar service `app/Services/Voice/VoiceIntentResolver.php`:
   ```php
   <?php

   namespace App\Services\Voice;

   use App\Enums\VoiceIntent;

   class VoiceIntentResolver
   {
       private const MIN_CONFIDENCE = 0.5;

       /** @var array<string, VoiceIntent> */
       private const SPEECH_PATTERNS = [
           // Interested
           'sim' => VoiceIntent::Interested,
           'tenho interesse' => VoiceIntent::Interested,
           'quero' => VoiceIntent::Interested,
           'pode mandar' => VoiceIntent::Interested,
           'manda' => VoiceIntent::Interested,
           'aceito' => VoiceIntent::Interested,

           // OptOut
           'nao quero receber' => VoiceIntent::OptOut,
           'nao ligue' => VoiceIntent::OptOut,
           'remover' => VoiceIntent::OptOut,
           'parar' => VoiceIntent::OptOut,
           'me tira' => VoiceIntent::OptOut,
           'descadastrar' => VoiceIntent::OptOut,

           // Callback
           'depois' => VoiceIntent::Callback,
           'mais tarde' => VoiceIntent::Callback,
           'me liga amanha' => VoiceIntent::Callback,
           'outra hora' => VoiceIntent::Callback,
           'agora nao' => VoiceIntent::Callback,

           // NoInterest
           'nao' => VoiceIntent::NoInterest,
           'nao tenho interesse' => VoiceIntent::NoInterest,
           'nao obrigado' => VoiceIntent::NoInterest,
       ];

       /**
        * @param array<string, array{action: string, label: string}> $dtmfActions
        */
       public function resolve(string $digits, string $speech, ?float $confidence, array $dtmfActions): VoiceIntent
       {
           if ($digits !== '') {
               return $this->mapDtmf($digits, $dtmfActions);
           }

           if ($speech === '') {
               return VoiceIntent::Unknown;
           }

           if ($confidence !== null && $confidence < self::MIN_CONFIDENCE) {
               return VoiceIntent::Unknown;
           }

           return $this->matchSpeech($speech);
       }

       /**
        * @param array<string, array{action: string, label: string}> $dtmfActions
        */
       private function mapDtmf(string $digits, array $dtmfActions): VoiceIntent
       {
           $action = $dtmfActions[$digits]['action'] ?? null;

           return match ($action) {
               'interested' => VoiceIntent::Interested,
               'optout' => VoiceIntent::OptOut,
               'callback' => VoiceIntent::Callback,
               'hangup' => VoiceIntent::NoInterest,
               default => VoiceIntent::Unknown,
           };
       }

       private function matchSpeech(string $speech): VoiceIntent
       {
           $normalized = mb_strtolower(trim($speech));

           // Check longer phrases first to avoid partial match collisions
           $patterns = self::SPEECH_PATTERNS;
           uksort($patterns, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

           foreach ($patterns as $needle => $intent) {
               if (str_contains($normalized, $needle)) {
                   return $intent;
               }
           }

           return VoiceIntent::Unknown;
       }
   }
   ```

### Testes

Criar `tests/Unit/Services/Voice/VoiceIntentResolverTest.php`:

- `dtmf 1 maps to action defined in campaign`.
- `dtmf 9 with no mapped action returns Unknown`.
- `speech "sim" returns Interested`.
- `speech "tenho interesse" returns Interested`.
- `speech "nao quero receber ligacao" returns OptOut` (pega "nao quero receber").
- `speech "depois me liga" returns Callback`.
- `speech "nao" returns NoInterest`.
- `speech with confidence 0.2 returns Unknown`.
- `speech with confidence null is accepted`.
- `empty input returns Unknown`.
- `dtmf takes precedence over speech when both present`.

### Aceite

- [ ] DTMF e fala convergem para mesmo enum.
- [ ] Confianca baixa nao gera Interested.
- [ ] Pode adicionar nova frase no array `SPEECH_PATTERNS` sem mexer em controller.
- [ ] Todos os testes unit passam.

---

## Fase 2.4 — Repeticao inteligente e fallback

Estado: Nao iniciada.

### Por que

Se nao recebe resposta, encerra direto. Uma URA melhor da uma segunda chance educada.

### Pre-requisitos

Fase 2.2 e 2.3 concluidas.

### Passos

1. Migration: `php artisan make:migration add_gather_attempts_to_voice_campaign_calls --no-interaction`
   ```php
   Schema::table('voice_campaign_calls', function (Blueprint $table) {
       $table->unsignedTinyInteger('gather_attempts')->default(0)->after('speech_confidence');
   });
   ```

2. Adicionar em `$fillable` e cast de `VoiceCampaignCall`.

3. Editar `app/Http/Controllers/IvrController.php::handleGather`:
   - Quando `intent === VoiceIntent::Unknown` e a chamada for por entrada vazia (digits e speech ambos vazios), redirecionar para retry se `gather_attempts < 2`:
     ```php
     if ($intent === VoiceIntent::Unknown && $digits === '' && $speechResult === '' && $voiceCampaignCall->gather_attempts < 2) {
         $voiceCampaignCall->increment('gather_attempts');
         $twiml = new \Twilio\TwiML\VoiceResponse;
         $gather = $twiml->gather([
             'input' => 'dtmf speech',
             'language' => 'pt-BR',
             'numDigits' => '1',
             'timeout' => '5',
             'speechTimeout' => 'auto',
             'actionOnEmptyResult' => true,
             'action' => route('ivr.dtmf', $voiceCampaignCall),
             'method' => 'POST',
         ]);
         $gather->say('Desculpe, nao entendi. Por favor, responda agora ou pressione 1 para falar com a gente.', [
             'language' => 'pt-BR',
             'voice' => $voice,
         ]);
         $twiml->say('Nao recebi resposta. Ate logo!', ['language' => 'pt-BR', 'voice' => $voice]);
         $twiml->hangup();
         return response($twiml->asXML(), 200)->header('Content-Type', 'text/xml');
     }
     ```

4. Quando `gather_attempts >= 2` e ainda Unknown, marcar status `no_input`:
   - Adicionar status na lista de valores possiveis (so documentar — nao ha enum de status).
   - No fallback de `match`, em vez de `Unknown` generico, antes de `$twiml->say('Nao entendi...')` setar:
     ```php
     $voiceCampaignCall->update(['status' => 'no_input']);
     ```

### Testes

Adicionar em `tests/Feature/IvrControllerTest.php`:

- `first empty gather triggers retry`.
- `second empty gather triggers second retry` (gather_attempts=1 -> 2).
- `third empty gather sets status to no_input and hangs up`.
- `non-empty input does not increment gather_attempts`.

### Aceite

- [ ] Chamada nao entra em loop.
- [ ] Usuario tem segunda chance.
- [ ] Status `no_input` registrado quando aplicavel.
- [ ] Todos os testes passam.

---

## Fase 2.5 — Whitelist de TTS e validacao

Estado: Nao iniciada.

### Por que

Hoje qualquer string pode ser salva em `tts_voice` e a chamada quebra em runtime se a Twilio rejeitar a combinacao voz/idioma. Sem fallback. Sem preview controlado.

### Pre-requisitos

Fase 2.4 concluida.

### Passos

1. Adicionar em `config/services.php`, dentro de `'twilio'`:
   ```php
   'voices' => [
       'Google.pt-BR-Standard-A',
       'Google.pt-BR-Standard-B',
       'Google.pt-BR-Standard-C',
       'Google.pt-BR-Standard-D',
       'Google.pt-BR-Wavenet-A',
       'Google.pt-BR-Wavenet-B',
       'Google.pt-BR-Wavenet-C',
       'Google.pt-BR-Wavenet-D',
       'Polly.Camila-Neural',
       'Polly.Vitoria',
       'Polly.Ricardo',
   ],
   'default_voice' => 'Google.pt-BR-Standard-A',
   ```

2. Form Request `app/Http/Requests/StoreVoiceCampaignRequest.php` (e o equivalente Update se existir):
   - Adicionar regra:
     ```php
     'tts_voice' => ['nullable', 'string', \Illuminate\Validation\Rule::in(config('services.twilio.voices'))],
     ```

3. Em `app/Http/Controllers/IvrController.php`, todos os pontos que usam `$campaign->tts_voice ?? 'Google.pt-BR-Standard-A'`:
   - Trocar fallback hardcoded por `config('services.twilio.default_voice')`.

4. UI `resources/js/pages/campanhas-voz/Create.vue`:
   - Trocar input de texto livre por `<select>` populado com `voices` vindas do controller `create`.
   - Em `VoiceCampaignController::create`, passar:
     ```php
     'availableVoices' => config('services.twilio.voices'),
     'defaultVoice' => config('services.twilio.default_voice'),
     ```

5. Avisar quando script for longo:
   - Em `Create.vue`, calcular `greeting_template.length`. Se passar de 280 caracteres, mostrar aviso amarelo "Mensagem longa pode reduzir taxa de retencao".

6. Preview ja existente em `voz/Index.vue` deve continuar funcionando (Google TTS local). Nao mudar comportamento.

### Testes

Adicionar em `tests/Feature/Controllers/VoiceCampaignControllerTest.php`:

- `store rejects voice not in whitelist`.
- `store accepts voice in whitelist`.
- `store accepts null voice and uses default at runtime`.

### Aceite

- [ ] Apenas vozes da whitelist podem ser salvas.
- [ ] Aviso de script longo aparece.
- [ ] Fallback usa `config('services.twilio.default_voice')`.
- [ ] Todos os testes passam.

---

## Fase 2.6 — Origem do lead na conversa

Estado: Nao iniciada.

### Por que

Hoje, lead criado pelo `SendPostCallWhatsAppJob` e indistinguivel de lead receptivo organico. Operador e agente IA precisam saber que veio de URA para tom adequado.

### Pre-requisitos

Fase 2.5 concluida.

### Passos

1. Migration: `php artisan make:migration add_source_to_leads --no-interaction`
   ```php
   Schema::table('leads', function (Blueprint $table) {
       $table->string('source_channel', 32)->nullable()->after('campaign_id');
       $table->unsignedBigInteger('source_reference_id')->nullable()->after('source_channel');
       $table->index(['tenant_id', 'source_channel']);
   });
   ```

2. Editar `app/Models/Lead.php`:
   - Adicionar `source_channel` e `source_reference_id` em `$fillable`.

3. Editar `app/Jobs/SendPostCallWhatsAppJob.php::handle`:
   - Dentro do `Lead::firstOrCreate`, adicionar nos `$attributes`:
     ```php
     $attributes = [
         'agent_id' => $whatsappInstance->agent_id,
         'modo' => 'receptivo',
         'source_channel' => 'voice',
         'source_reference_id' => $call->id,
     ];
     ```
   - Apos o `firstOrCreate`, se o lead existia mas nao tinha `source_channel` setado, popular agora:
     ```php
     if ($lead->source_channel === null) {
         $lead->update([
             'source_channel' => 'voice',
             'source_reference_id' => $call->id,
         ]);
     }
     ```

4. UI conversas: editar lista de conversas (`resources/js/pages/conversas/Index.vue` ou equivalente — buscar arquivos com `grep -rn "lead" resources/js/pages/conversas/`):
   - Onde renderiza cada lead, mostrar badge se `source_channel === 'voice'`:
     ```vue
     <span v-if="lead.source_channel === 'voice'" class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800">URA</span>
     ```
   - Em pagina de detalhe da conversa, se `source_channel === 'voice'`, fazer query no backend para puxar `VoiceCampaignCall::find($lead->source_reference_id)` e exibir contexto: nome da campanha, data, intent detectado, transcricao da fala se houver.

5. Backend: editar controller que renderiza conversa (buscar com `grep -rn "Conversa" app/Http/Controllers/`). Adicionar prop:
   ```php
   'voiceCallContext' => $lead->source_channel === 'voice'
       ? \App\Models\VoiceCampaignCall::with('voiceCampaign')->find($lead->source_reference_id)
       : null,
   ```

### Testes

Adicionar em `tests/Feature/Jobs/SendPostCallWhatsAppJobTest.php`:

- `creates lead with source_channel=voice and source_reference_id`.
- `existing lead without source_channel gets updated to voice on second voice call`.
- `existing lead with source_channel set retains existing value`.

### Aceite

- [ ] Lead originado por URA tem `source_channel='voice'` e `source_reference_id`.
- [ ] Conversa lista mostra badge "URA" para esses leads.
- [ ] Pagina de detalhe da conversa mostra origem (campanha, data).
- [ ] Todos os testes passam.

---

## Fase 2.7 — Dashboard de funil de voz

Estado: Nao iniciada.

### Por que

Operador precisa ver o funil completo (chamadas -> atendidas -> interessadas -> WhatsApp -> respostas -> qualificados) em uma so tela para diagnosticar problema.

### Pre-requisitos

Fase 2.6 concluida.

### Passos

1. Service novo `app/Services/Voice/VoiceCampaignFunnelService.php`:
   ```php
   <?php

   namespace App\Services\Voice;

   use App\Models\Lead;
   use App\Models\VoiceCampaign;

   class VoiceCampaignFunnelService
   {
       /**
        * @return array<string, int|float>
        */
       public function metrics(VoiceCampaign $campaign): array
       {
           $callIds = $campaign->calls()->where('is_test', false)->pluck('id');

           $whatsappLeads = Lead::query()
               ->where('source_channel', 'voice')
               ->whereIn('source_reference_id', $callIds)
               ->count();

           $respondedLeads = Lead::query()
               ->where('source_channel', 'voice')
               ->whereIn('source_reference_id', $callIds)
               ->whereNotNull('last_inbound_at')
               ->count();

           $qualifiedLeads = Lead::query()
               ->where('source_channel', 'voice')
               ->whereIn('source_reference_id', $callIds)
               ->where('status', 'qualificado')
               ->count();

           return [
               'total_calls' => $campaign->total_calls,
               'answered' => $campaign->total_answered,
               'voicemail' => $campaign->total_voicemail,
               'fax' => $campaign->total_fax,
               'unknown' => $campaign->total_unknown,
               'no_answer' => $campaign->total_no_answer,
               'failed' => $campaign->total_failed,
               'interested' => $campaign->total_interested,
               'whatsapp_sent' => $whatsappLeads,
               'whatsapp_responded' => $respondedLeads,
               'qualified' => $qualifiedLeads,
               'answer_rate' => $campaign->answerRate(),
               'interest_rate' => $campaign->interestRate(),
               'response_rate' => $whatsappLeads > 0 ? round(($respondedLeads / $whatsappLeads) * 100, 2) : 0.0,
               'qualified_rate' => $respondedLeads > 0 ? round(($qualifiedLeads / $respondedLeads) * 100, 2) : 0.0,
               'estimated_cost' => $this->estimatedCost($campaign),
               'estimated_cost_unit' => $campaign->calls()->whereNotNull('price_unit')->value('price_unit'),
           ];
       }

       private function estimatedCost(VoiceCampaign $campaign): float
       {
           return (float) $campaign->calls()
               ->where('is_test', false)
               ->whereNotNull('price')
               ->sum('price');
       }
   }
   ```

2. Editar `VoiceCampaignController::show`:
   - Adicionar:
     ```php
     'funnel' => app(\App\Services\Voice\VoiceCampaignFunnelService::class)->metrics($voiceCampaign),
     ```

3. UI `resources/js/pages/campanhas-voz/Show.vue`:
   - Adicionar secao "Funil" no topo da pagina com cards:
     - Total chamadas
     - Atendidas (humano) — `answered_rate%`
     - Caixa postal / Fax / Desconhecido
     - Nao atendidas
     - Falhas
     - Interessados — `interest_rate%`
     - WhatsApp enviados
     - Responderam no WhatsApp — `response_rate%`
     - Qualificados — `qualified_rate%`
     - Custo estimado (em `price_unit`)
   - Layout em grid responsivo.

### Testes

Criar `tests/Unit/Services/Voice/VoiceCampaignFunnelServiceTest.php`:

- `metrics aggregates call counts correctly`.
- `metrics excludes test calls from cost`.
- `metrics calculates rates with division by zero protection`.
- `metrics counts leads with last_inbound_at as responded`.

### Aceite

- [ ] Tela de campanha mostra funil completo.
- [ ] Custo estimado nao inclui chamadas de teste.
- [ ] Taxas com divisao por zero retornam 0.
- [ ] Todos os testes passam.

---

## Fase 2.8 — Janela de horario e retry controlado

Estado: Nao iniciada.

### Por que

Sem controle de horario, campanha pode ligar 23h. Sem max_attempts, mesma pessoa recebe 5 ligacoes. Risco de reclamacao e regulatorio.

### Pre-requisitos

Fase 2.7 concluida.

### Passos

1. Migration: `php artisan make:migration add_window_to_voice_campaigns --no-interaction`
   ```php
   Schema::table('voice_campaigns', function (Blueprint $table) {
       $table->unsignedTinyInteger('send_hour_start')->default(9)->after('delay_between_calls_ms');
       $table->unsignedTinyInteger('send_hour_end')->default(20)->after('send_hour_start');
       $table->json('allowed_days')->nullable()->after('send_hour_end'); // ex: [1,2,3,4,5] (Mon-Fri, 0=Sun)
       $table->string('timezone', 64)->default('America/Sao_Paulo')->after('allowed_days');
       $table->unsignedTinyInteger('max_attempts')->default(1)->after('timezone');
   });

   Schema::table('voice_campaign_calls', function (Blueprint $table) {
       $table->unsignedTinyInteger('attempt_number')->default(1)->after('gather_attempts');
       $table->timestamp('next_attempt_at')->nullable()->after('attempt_number');
   });
   ```

2. Editar `app/Models/VoiceCampaign.php`:
   - Adicionar campos em `$fillable` e casts.
   - Adicionar metodo:
     ```php
     public function isWithinSendingWindow(?\Carbon\Carbon $now = null): bool
     {
         $now = ($now ?? now())->setTimezone($this->timezone ?? 'America/Sao_Paulo');
         $hour = (int) $now->format('H');
         $weekday = (int) $now->format('w'); // 0=Sun .. 6=Sat

         $allowedDays = $this->allowed_days ?? [1, 2, 3, 4, 5];

         if (! in_array($weekday, $allowedDays, true)) {
             return false;
         }

         return $hour >= $this->send_hour_start && $hour < $this->send_hour_end;
     }
     ```

3. Editar `app/Jobs/PlaceOutboundCallJob.php::handle`:
   - Apos guard de `isSending`, adicionar:
     ```php
     if (! $voiceCampaign->isWithinSendingWindow() && ! $call->is_test) {
         $delaySeconds = $this->calculateDelayUntilNextWindow($voiceCampaign);
         self::dispatch($call)->delay(now()->addSeconds($delaySeconds));
         Log::info('PlaceOutboundCallJob: outside window, rescheduled', [
             'call_id' => $call->id,
             'delay_seconds' => $delaySeconds,
         ]);
         return;
     }
     ```
   - Adicionar metodo privado:
     ```php
     private function calculateDelayUntilNextWindow(\App\Models\VoiceCampaign $campaign): int
     {
         $tz = $campaign->timezone ?? 'America/Sao_Paulo';
         $now = now()->setTimezone($tz);
         $allowedDays = $campaign->allowed_days ?? [1, 2, 3, 4, 5];

         for ($i = 0; $i < 8; $i++) {
             $candidate = $now->copy()->addDays($i)->setTime($campaign->send_hour_start, 0);
             $weekday = (int) $candidate->format('w');
             if (in_array($weekday, $allowedDays, true) && $candidate->greaterThan($now)) {
                 return max(60, $candidate->diffInSeconds($now));
             }
         }
         return 3600;
     }
     ```

4. Editar `StoreVoiceCampaignRequest`:
   - Adicionar regras:
     ```php
     'send_hour_start' => ['nullable', 'integer', 'min:0', 'max:23'],
     'send_hour_end' => ['nullable', 'integer', 'min:1', 'max:24', 'gt:send_hour_start'],
     'allowed_days' => ['nullable', 'array'],
     'allowed_days.*' => ['integer', 'min:0', 'max:6'],
     'timezone' => ['nullable', 'string', 'timezone'],
     'max_attempts' => ['nullable', 'integer', 'min:1', 'max:5'],
     ```

5. Adicionar campos em `Create.vue` (formulario de criacao da campanha): hora inicio, hora fim, dias da semana (checkboxes), max_attempts (input).

6. Retry controlado (max_attempts):
   - Editar `IvrController::statusCallback`:
     - Quando `CallStatus = no-answer` e `! $alreadyCompleted`, verificar se `attempt_number < max_attempts`. Se sim, agendar nova tentativa:
       ```php
       if ($callStatus === 'no-answer' && ! $alreadyCompleted) {
           $campaign = $voiceCampaignCall->voiceCampaign;
           if ($voiceCampaignCall->attempt_number < $campaign->max_attempts && ! $voiceCampaignCall->is_test) {
               $retry = VoiceCampaignCall::create([
                   'voice_campaign_id' => $voiceCampaignCall->voice_campaign_id,
                   'contact_list_entry_id' => $voiceCampaignCall->contact_list_entry_id,
                   'phone' => $voiceCampaignCall->phone,
                   'contact_name' => $voiceCampaignCall->contact_name,
                   'interpolated_message' => $voiceCampaignCall->interpolated_message,
                   'status' => 'pending',
                   'attempt_number' => $voiceCampaignCall->attempt_number + 1,
                   'next_attempt_at' => now()->addHours(4),
               ]);
               PlaceOutboundCallJob::dispatch($retry)->delay(now()->addHours(4));
               Log::info('ivr.no_answer.retry_scheduled', [
                   'original_call_id' => $voiceCampaignCall->id,
                   'retry_call_id' => $retry->id,
                   'attempt' => $retry->attempt_number,
               ]);
           }
       }
       ```

### Testes

Criar `tests/Unit/Models/VoiceCampaignWindowTest.php`:

- `isWithinSendingWindow returns true at 10am on Tuesday`.
- `isWithinSendingWindow returns false at 22h`.
- `isWithinSendingWindow returns false on Sunday by default`.
- `isWithinSendingWindow respects custom allowed_days`.

Adicionar em `tests/Feature/Jobs/PlaceOutboundCallJobTest.php`:

- `reschedules call when outside window`.
- `places call immediately when inside window`.
- `test calls bypass window check`.

Adicionar em `tests/Feature/IvrControllerTest.php`:

- `no-answer schedules retry when attempt_number < max_attempts`.
- `no-answer does not retry when attempt_number == max_attempts`.

### Aceite

- [ ] Campanha nao liga fora da janela.
- [ ] Rechamadas sao limitadas por `max_attempts`.
- [ ] Test calls ignoram janela.
- [ ] Todos os testes passam.

---

# FASE 3: Operacao Comercial

Objetivo: dar ao time comercial controle de segmentacao, A/B e analise pos-chamada.

## Fase 3.1 — Segmentacao por template/segmento

Estado: Nao iniciada.

### Por que

A base pode ter origens diferentes (INSS, SIAPE, FGTS). Adaptar script por segmento aumenta conversao.

### Pre-requisitos

Fase 2.8 concluida.

### Passos

1. Migration: `php artisan make:migration create_voice_script_templates_table --no-interaction`
   ```php
   Schema::create('voice_script_templates', function (Blueprint $table) {
       $table->id();
       $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
       $table->string('name');
       $table->string('segment')->nullable(); // ex: inss, siape, fgts
       $table->text('greeting_template');
       $table->string('tts_voice')->nullable();
       $table->json('dtmf_actions')->nullable();
       $table->timestamps();
   });
   ```

2. Modelo, factory, controller CRUD basico (`VoiceScriptTemplateController`), pagina `/voz/templates` listando, criando e deletando.

3. Em `Create.vue` da campanha, adicionar dropdown "Carregar template" que pre-popula `greeting_template`, `tts_voice`, `dtmf_actions`.

4. `ContactList` ja tem `extra_data` em entries — em `voice_campaigns` adicionar coluna opcional `segment_filter`:
   ```php
   $table->string('segment_filter')->nullable()->after('contact_list_id');
   ```
   - Quando setado, `DispatchVoiceCampaignJob` filtra entries onde `JSON_EXTRACT(extra_data, '$.segment') = $campaign->segment_filter`.

### Testes

- `template can be created and reused in campaign`.
- `dispatch filters entries by segment_filter`.

### Aceite

- [ ] Operador escolhe template ao criar campanha.
- [ ] `segment_filter` filtra entries no dispatch.
- [ ] Relatorio mostra performance por template (puxar atraves de `voice_script_template_id` se adicionar FK na campanha — opcional).

---

## Fase 3.2 — A/B de script e voz

Estado: Nao iniciada.

### Por que

Sem A/B nao da pra otimizar. Comparar 2 scripts/vozes na mesma campanha.

### Pre-requisitos

Fase 3.1 concluida.

### Passos

1. Migration: `php artisan make:migration create_voice_campaign_variations_table --no-interaction`
   ```php
   Schema::create('voice_campaign_variations', function (Blueprint $table) {
       $table->id();
       $table->foreignId('voice_campaign_id')->constrained()->cascadeOnDelete();
       $table->string('label'); // ex: A, B
       $table->text('greeting_template');
       $table->string('tts_voice');
       $table->unsignedTinyInteger('weight')->default(50);
       $table->timestamps();
   });

   Schema::table('voice_campaign_calls', function (Blueprint $table) {
       $table->foreignId('voice_campaign_variation_id')->nullable()->constrained()->after('voice_campaign_id');
   });
   ```

2. Em `DispatchVoiceCampaignJob`, ao criar `VoiceCampaignCall`, sortear variante segundo `weight` e setar `voice_campaign_variation_id`.

3. Em `IvrController::script`, usar `interpolated_message` ja persistido (que sera derivado do template da variante quando criado pelo dispatch).

4. Dashboard funil compara variantes: agrupar por `voice_campaign_variation_id` em `VoiceCampaignFunnelService`.

### Testes

- `dispatch picks variation according to weight distribution`.
- `funnel groups metrics by variation`.

### Aceite

- [ ] Campanha pode ter 2+ variantes.
- [ ] Cada chamada guarda variante.
- [ ] Dashboard compara.

---

## Fase 3.3 — Transcricao pos-chamada

Estado: Nao iniciada.

### Por que

`SpeechResult` do Gather captura a primeira fala. Para casos com mais de um turno ou para analise de tom, precisa de transcricao pos-chamada.

### Pre-requisitos

Fase 3.2 concluida.

### Passos

1. Avaliar entre Twilio Conversational Intelligence vs gravacao + Whisper externo. **Decisao a ser tomada quando esta fase for ativada** — antes de comecar, atualizar este plano com a decisao escolhida.

2. Migration generica:
   ```php
   Schema::create('voice_call_transcripts', function (Blueprint $table) {
       $table->id();
       $table->foreignId('voice_campaign_call_id')->constrained()->cascadeOnDelete();
       $table->string('provider', 32);
       $table->text('transcript')->nullable();
       $table->text('summary')->nullable();
       $table->string('sentiment', 32)->nullable();
       $table->string('intent', 64)->nullable();
       $table->json('compliance_flags')->nullable();
       $table->json('raw_payload')->nullable();
       $table->timestamps();
   });
   ```

3. Job `ProcessCallTranscriptJob` que recebe transcricao via webhook do provider escolhido.

4. UI: exibir transcricao na pagina de detalhe da chamada e na conversa do lead correspondente.

### Aceite

- [ ] Cada chamada com fala relevante gera resumo.
- [ ] Resumo aparece no CRM.

---

## Fase 3.4 — Compliance e reputacao

Estado: Nao iniciada.

### Pre-requisitos

Fase 3.3 concluida (ou paralela, conforme decisao operacional).

### Passos

1. Auditoria de logs: garantir que `tenant_id`, `voice_campaign_id`, `voice_campaign_call_id`, `call_sid`, `phone` aparecem em todos os logs criticos (`Log::info` em `PlaceOutboundCallJob`, `IvrController::*`, `SendPostCallWhatsAppJob`, `ReconcileStuckVoiceCallsJob`, `FetchTwilioCallPriceJob`).

2. Blacklist global por tenant: tabela `tenant_voice_blacklist`:
   ```php
   Schema::create('tenant_voice_blacklist', function (Blueprint $table) {
       $table->id();
       $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
       $table->string('phone');
       $table->string('reason', 64)->nullable();
       $table->timestamps();
       $table->unique(['tenant_id', 'phone']);
   });
   ```
   - Em `DispatchVoiceCampaignJob` filtrar entries cujo `phone` esteja na blacklist do tenant.
   - Em `handleOptout`, alem de marcar `ContactListEntry`, inserir tambem na blacklist.

3. Auto-pause em condicoes criticas (`app/Jobs/MonitorVoiceCampaignHealthJob.php`):
   - Schedule a cada 5 min para campanhas em `sending`.
   - Pausa se: taxa de `total_failed / total_calls > 30%` em janela recente, ou `total_unknown / total_answered > 50%` (AMD ruim), ou `next status_callback older than 30min`.

4. Documentar rotina de revisao de reclamacoes — fora de codigo, criar `docs/ura-compliance-runbook.md`.

### Aceite

- [ ] Blacklist por tenant respeitada.
- [ ] Opt-out alimenta blacklist.
- [ ] Auto-pause funciona.
- [ ] Logs auditaveis por `call_sid`.

---

# FASE 4: Inteligencia Avancada

Objetivo: depois de validar a URA simples, evoluir para conversa por turnos, VirtualAgent ou Media Streams.

**Esta fase nao deve ser iniciada antes de 3 meses de operacao real e volume suficiente para justificar custo.** Quando ativada, atualizar este documento com analise de viabilidade e decisao de provider antes de codar.

Estrutura recomendada (alto nivel — sem prescricao detalhada porque depende de decisoes operacionais futuras):

- 4.1 Conversa por turnos (loop de Gather speech + agente IA decide proximo TwiML).
- 4.2 Avaliar Twilio `<Connect><VirtualAgent>` com Dialogflow CX.
- 4.3 Avaliar Twilio Media Streams para baixa latencia.

---

# Checklist Go-Live (a cada deploy de fase nova)

Antes de testar em telefone real:

- [ ] `TWILIO_ACCOUNT_SID` configurado.
- [ ] `TWILIO_AUTH_TOKEN` configurado.
- [ ] `TWILIO_PHONE_NUMBER` configurado.
- [ ] `APP_URL` publico HTTPS.
- [ ] `TwilioConfigValidator::validate()` retorna `valid=true`.
- [ ] Webhooks Twilio acessiveis (testar com `curl` na URL publica).
- [ ] `X-Twilio-Signature` validando (verificar `ValidateTwilioSignature`).
- [ ] `statusCallbackEvent` configurado conforme fase.
- [ ] Fila `campaigns` rodando (Horizon supervisor).
- [ ] Fila `messages` rodando.
- [ ] Schedule `reconcile-stuck-voice-calls` rodando.
- [ ] Numero de teste validado.
- [ ] WhatsApp vinculado a instancia de voz.
- [ ] Template Meta aprovado (se Meta Cloud).
- [ ] `ContactList` pequena (<5 telefones internos) para primeira validacao.
- [ ] Script curto validado por audio preview.
- [ ] Opt-out testado (Fase 0.1).
- [ ] AMD habilitado e callback testado (Fase 2.1+).
- [ ] Dashboard funil conferido (Fase 2.7+).

Apos primeiro teste real:

- [ ] Conferir logs por `call_sid`.
- [ ] Conferir status final da chamada.
- [ ] Conferir `CallStatus`, `CallDuration`, `AnsweredBy`, `SipResponseCode` em `status_callback_payload`.
- [ ] Conferir custo apos 5 min (job `FetchTwilioCallPriceJob` rodou).
- [ ] Conferir se interessados receberam WhatsApp.
- [ ] Conferir se leads apareceram em `/conversas` com badge URA.
- [ ] Conferir se respostas no WhatsApp atualizam `last_inbound_at`.

---

# Riscos Conhecidos

- **Custo por chamada cresce rapido**. Mitigacao: `max_attempts`, `delay_between_calls_ms`, dashboard de custo (`Fase 2.7`), auto-pause (`Fase 3.4`).
- **Caixa postal infla metricas sem AMD**. Mitigacao: `Fase 2.1`.
- **Voz artificial ruim derruba interesse**. Mitigacao: whitelist + preview (`Fase 2.5`).
- **Script longo aumenta abandono**. Mitigacao: aviso no UI quando passa de 280 chars (`Fase 2.5`).
- **Falta de opt-out gera reclamacao**. Mitigacao: `Fase 0.1` + blacklist (`Fase 3.4`).
- **IA em tempo real cedo demais aumenta complexidade**. Mitigacao: `Fase 4` so apos volume real.
- **Webhook mal configurado faz chamadas completarem sem update**. Mitigacao: `ReconcileStuckVoiceCallsJob` (`Fase 1.4`).
- **Twilio retry duplica contadores**. Mitigacao: idempotencia (`Fase 0.2`).
- **Reputacao do numero Twilio degrada em escala**. Mitigacao parcial: respeitar opt-out, blacklist, janela. Mitigacao completa exige multi-numero — fora de escopo desta iteracao.

---

# Notas de Aderencia Twilio

- No SDK PHP da Twilio, parametros de `Call::create` usam `lowerCamelCase`: `machineDetection`, `asyncAmd`, `asyncAmdStatusCallback`, `statusCallbackEvent`.
- `CallDuration` e enviado em eventos terminais.
- `SipResponseCode` e util para diagnostico de falhas; salvar quando enviado.
- `ErrorCode` e `ErrorMessage` nao sao obrigatorios em status callbacks normais — tratar como nullable.
- `Price` e `PriceUnit` pertencem ao recurso `Call` e podem ser populados depois — buscar via `client->calls($sid)->fetch()` apos delay.
- `Confidence` em `<Gather input="speech">` e nullable.
- AMD assincrono melhora tempo inicial, mas classificacao chega depois do primeiro prompt — daí a `Opcao B` no plano (`Fase 2.1`).
- Todo webhook publico Twilio deve validar `X-Twilio-Signature`. Ja implementado em `app/Http/Middleware/ValidateTwilioSignature.php`.

---

# Referencias Twilio

- [Twilio Programmable Voice / TwiML](https://www.twilio.com/docs/voice/twiml)
- [Twilio Call Resource](https://www.twilio.com/docs/voice/api/call-resource)
- [Twilio statusCallback e statusCallbackEvent](https://www.twilio.com/docs/voice/api/call-resource#statuscallback)
- [Twilio `<Gather>`](https://www.twilio.com/docs/voice/twiml/gather)
- [Twilio `<Gather>` input dtmf+speech](https://www.twilio.com/docs/voice/twiml/gather#input)
- [Twilio `<Say>`](https://www.twilio.com/docs/voice/twiml/say)
- [Twilio Text-to-Speech voices](https://www.twilio.com/docs/voice/twiml/say/text-speech)
- [Twilio Answering Machine Detection](https://www.twilio.com/docs/voice/answering-machine-detection)
- [Twilio Async AMD](https://www.twilio.com/docs/voice/answering-machine-detection#asyncamd)
- [Twilio Conversational Intelligence](https://www.twilio.com/docs/voice/intelligence)
- [Twilio request validation (X-Twilio-Signature)](https://www.twilio.com/docs/usage/security#validating-requests)

---

# Checklist de Atualizacao Deste Documento (a IA executora deve seguir)

A cada fase concluida:

1. Atualizar `FASES CONCLUIDAS` no cabecalho — adicionar a fase concluida em ordem.
2. Atualizar `FASE ATUAL` para a proxima fase, com `(n)` indicando "nao iniciada".
3. Atualizar `PROXIMA FASE` para o numero da proxima fase.
4. Atualizar linha `Ultima atualizacao` com data + nome do arquivo de execucao criado.
5. Mudar o campo `Estado: Nao iniciada` da fase concluida para `Estado: Concluida em <data>`.
6. Marcar todos os checkboxes de `Aceite` da fase.
7. Criar `docs/ura-modernization-fase-X.Y.md` com:
   - O que foi feito.
   - Arquivos criados/modificados.
   - Migrations executadas.
   - Testes adicionados (lista) e comando para rodar.
   - Decisoes de runtime tomadas (se houver).
   - Pendencias ou observacoes para a proxima fase.
8. Commitar tudo com mensagem `feat(voice-ura): fase X.Y concluida — <resumo curto>`.

Ao iniciar uma fase:

1. Mudar `FASE ATUAL` de `(n)` para `(s)`.
2. Mudar `Estado: Nao iniciada` da fase para `Estado: Em andamento desde <data>`.
3. Ao terminar, seguir o checklist acima.

Se for necessario tomar uma decisao nao coberta por `Decisoes Ja Tomadas`:

1. **Parar a execucao**.
2. Documentar a decisao necessaria no arquivo de execucao da fase.
3. Pedir confirmacao ao usuario.
4. Atualizar `Decisoes Ja Tomadas` deste documento com a resposta.
