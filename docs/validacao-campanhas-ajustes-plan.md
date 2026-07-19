# Plano de Implementação — Ajustes da Validação de Campanhas WhatsApp

> **Contexto:** durante a validação do fluxo de campanhas Meta Cloud (campanha "Teste1" rodou com sucesso: 2 enviados, 1 lido, 1 falha por telefone inválido), três lacunas de UX/funcionalidade foram identificadas. Este documento é o plano técnico para implementá-las em outra sessão. Cada ajuste é um slice independente — pode ser implementado, testado e commitado separadamente, na ordem apresentada.

---

## Visão geral dos ajustes

| # | Ajuste | Tipo | Esforço | Risco |
|---|--------|------|---------|-------|
| 1 | Formato de data legível na tela da campanha | Frontend only | Baixo | Baixo |
| 2 | Template de campanha visível em /conversas + trava de janela 24h | Backend + Frontend | Alto | Médio |
| 3 | Importação de listas: aliases de coluna de telefone | Backend only | Baixo | Baixo |

Regras do projeto que se aplicam a todos os slices:

- Todo slice termina com teste Pest novo/atualizado e `php artisan test --compact --filter=<nome>`.
- Rodar `vendor/bin/pint --dirty --format agent` antes de finalizar.
- Frontend: `npm run build` (ou avisar o usuário para rodar `composer run dev`).
- Convenções: Form Requests para validação, PHPDoc em vez de comentário inline, factories nos testes.

---

## Ajuste 1 — Formato de data na tela de campanha

### Sintoma

A tabela "Destinatários" em `/campanhas/{id}` exibe timestamps crus: `2026-07-18T01:49:19.000000Z`.

### Causa raiz

[Show.vue:743-749](../resources/js/pages/campanhas/Show.vue) renderiza `msg.sent_at`, `msg.delivered_at`, `msg.read_at` diretamente como string. O backend (`CampaignPagePropsBuilder`) serializa os Carbon do Eloquent no formato ISO-8601 UTC padrão do Laravel — comportamento correto; a formatação deve ficar no frontend (converte para o fuso do navegador de graça).

### Implementação

1. **Criar utilitário compartilhado** `resources/js/lib/datetime.ts` (não existe formatter compartilhado hoje — `lib/utils.ts` só tem `cn` e `toUrl`; várias páginas duplicam `formatDate` local):

```ts
const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

export function formatDateTime(value: string | null | undefined): string | null {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return dateTimeFormatter.format(date); // "18/07/2026 22:49" no fuso do navegador
}
```

2. **Aplicar em `campanhas/Show.vue`** nas três colunas (`sent_at`, `delivered_at`, `read_at`): `{{ formatDateTime(msg.sent_at) ?? '—' }}`. Varrer o restante do arquivo por outros timestamps crus (ex.: `scheduled_at`, `started_at`, `completed_at` no cabeçalho, se exibidos).

3. **Não migrar as outras 16 páginas** que têm `formatDate` local neste slice — fora de escopo. Apenas deixar o utilitário disponível para adoção incremental.

### Testes

Sem infra de teste JS no projeto (não há vitest). Verificação: `npm run build` + smoke visual na tela da campanha. Nenhum teste PHP necessário (backend intocado).

### Critério de aceite

Colunas "Enviado em / Entregue em / Lido em" exibem `dd/mm/aaaa hh:mm` no fuso local; célula vazia continua `—`.

---

## Ajuste 2 — Template de campanha em /conversas + trava de janela 24h

### Sintoma

1. O template disparado pela campanha **não aparece** na thread de atendimento em `/conversas`.
2. A tela de conversa **permite digitar/enviar** mensagem livre mesmo com a janela de 24h fechada (Meta rejeitaria). Referência visual desejada: banner "Janela de 24h fechada. Para falar com o cliente, envie um template aprovado ou aguarde uma nova mensagem." + composer bloqueado + seletor de template (screenshot de outro sistema anexado na demanda).

### Causa raiz

- **Parte A (mensagem invisível):** `SendCampaignMessageJob` envia direto via `provider->sendTemplate()` e grava apenas `CampaignMessage`. Não passa pelo `WhatsappOutboxService` nem grava `ConversationTimelineMessage`. Além disso, `Lead` só nasce em mensagem *inbound* (`IncomingConversationPersister::persist`, [IncomingConversationPersister.php:166](../app/Services/IncomingConversationPersister.php)) — para um destinatário de campanha que nunca respondeu, não existe Lead, logo não existe conversa para exibir.
- **Parte B (sem trava):** o status da janela **já existe no backend** — `WhatsAppConversationWindowResolver::resolve()` retorna `service_window.status`, `template_required`, `free_entry_point` etc., e `ConversationPanelPropsBuilder` já injeta isso como prop `conversationWindow` (consumida hoje só pelo `LeadDetailsPanel.vue` como card informativo). `ConversationThread.vue` ignora a prop e `ConversasController::sendMessage` / `SendOperatorMessageAction` não validam a janela server-side.

### Decisão de design (Parte A) — evitar poluir /conversas

Criar Lead eagerly para **todo** destinatário de campanha inundaria a tela de atendimento com milhares de conversas mortas (campanhas podem ter 100k contatos). Estratégia híbrida recomendada:

1. **Lead já existe** (telefone + tenant): gravar a mensagem de template na timeline imediatamente após o envio.
2. **Lead não existe:** não criar. Quando o destinatário **responder** (o que já cria o Lead via `IncomingConversationPersister`), fazer **backfill** dos templates de campanha enviados recentemente para aquele telefone, preservando o `sent_at` original como `created_at` da timeline row.

Assim /conversas só mostra conversas reais, mas toda conversa real exibe o template que a originou — inclusive o caso do screenshot (lead `5521996902935` já existia e mesmo assim nada apareceu; com o item 1 passa a aparecer).

### Implementação — Parte A

**A1. Renderizador de template** — portar `WhatsappTemplateRenderer` do Estalo (ver B3 e o "Mapa Estalo → Aria"); um único renderer serve a campanha (aqui) e o envio manual (B3):
- Input campanha: `WhatsappTemplate->components` + `CampaignMessage->template_params_resolved` (`['1' => 'valor', ...]`) convertido para o shape de seções do renderer (`['body' => ['1' => 'valor', ...]]` — o mapping de campanha só preenche body/header numerados).
- Output: `render()['text']` = texto final como o cliente recebeu (header + body + footer + botões rotulados). Parâmetro ausente: no fluxo campanha, usar `preview()` (fallback para exemplos/placeholder) em vez de `render()` estrito, para nunca quebrar a gravação da timeline.

**A2. Gravação na timeline pós-envio** em `SendCampaignMessageJob::handle`, logo após `markSentIfOwned` ter sucesso (mesmo ponto onde já loga `outbound_sent`):
- Buscar Lead: `Lead::withoutGlobalScopes()->where('tenant_id', $campaign->tenant_id)->where('whatsapp', $destination)->first()` (atenção: `$destination` é o telefone normalizado por `PhoneNumberValidator::normalize`; o Lead inbound usa o mesmo formato E.164 sem `+` — confirmar com um lead real antes de fechar o predicado).
- Se existir: `ConversationTimelineService::record()` com `direction: 'outbound'`, `senderType: 'system'`, `source: 'campaign'`, `status: 'sent'`, `providerMessageId: $providerMessageId`, `body:` texto renderizado (A1). Colunas `sender_type`/`source` são `string(20)`/`string(30)` sem constraint ([migration](../database/migrations/2026_05_09_000001_create_conversation_timeline_messages_table.php)) — `'system'`/`'campaign'` são valores novos válidos. `toFrontendMessage` mapeia sender desconhecido para `role: 'assistant'` (bolha do lado direito) — comportamento desejado. Em seguida `ConversationTimelineService::broadcast()` para tempo real.
- Falha na gravação da timeline **não pode** falhar o job (o envio já ocorreu): try/catch com `Log::warning`.
- Se o Lead existir, também atualizar `whatsapp_instance_id` do lead se estiver nulo (facilita a Parte B e o envio manual posterior).

**A3. Backfill no inbound** — em `IncomingConversationPersister`, após criar Lead **novo** (só no branch `Lead::create`, não no `$existing`):
- Localizar mensagens de campanha recentes para o telefone: `CampaignMessage` com status em `['sent','delivered','read']`, `provider_message_id` não nulo, cujo `contactListEntry.phone == $phone` e campanha do mesmo tenant, janela de ex. últimos 30 dias, limite ~5.
- Para cada uma, inserir timeline row idêntica à de A2 mas com `created_at`/`updated_at` = `sent_at` original (usar `ConversationTimelineMessage::create` direto ou estender `record()` com `?Carbon $occurredAt`), e `status` = status atual da CampaignMessage.
- Guard de idempotência: pular se já existe timeline row com o mesmo `provider_message_id` para o lead.
- Executar fora da transação de criação do lead (mesmo padrão do `contactSync`): falha loga e não derruba o inbound.

**A4. Status de entrega/leitura na timeline** — `ProcessCampaignDeliveryEventJob::syncOutbox` hoje só atualiza timeline via outbox (que campanha não usa). Adicionar fallback: se nenhum outbox encontrado, procurar `ConversationTimelineMessage` por `provider_message_id` (+ tenant) e atualizar `status` + `broadcast()`. Mapear `read` → `read`, `delivered` → `delivered`, `failed` → `failed`.

### Implementação — Parte B (trava de janela)

> **Referência de implementação:** o sistema **Estalo** (`D:\DOCS\PROMOSYS\Estalo_hub\estalo`) já implementa exatamente essa funcionalidade — o screenshot de referência da demanda é dele. Os padrões abaixo foram alinhados ao Estalo; a seção "Mapa Estalo → Aria" no final lista os arquivos-fonte para consulta durante a implementação.

**B1. Frontend — `ConversationThread.vue`:**
- Receber/consumir `conversationWindow` (já disponível nas props da conversa — ver `types.ts:86-134` e como `LeadDetailsPanel` a lê).
- **Padrão Estalo (adotar):** não usar um boolean estático do server; o server envia o **deadline** (`service_window.expires_at`, já presente no payload do resolver) e o client computa `windowClosed` ao vivo (`new Date(expires_at) <= now`, reavaliado por timer de ~30s). No Estalo (`Atendimento/Index.vue:833`), a chegada de inbound novo via broadcast recalcula o deadline localmente (`sentAt + 24h`) — replicar isso no listener de `NewConversationMessage` quando `direction === 'inbound'`, reabrindo a janela sem reload. Isso elimina o problema de reatividade da prop Inertia.
- Quando `windowClosed`:
  - Banner de aviso acima do composer comunicando: janela de 24h fechada, só template aprovado ou aguardar nova mensagem do cliente. **Não copiar o visual/texto do Estalo** — mesma funcionalidade, redação e apresentação próprias da aria (componentes e tokens já usados em `conversas/partials`). Referência de comportamento: `ChatInput.vue:455-487` do Estalo.
  - Composer travado (textarea, anexo e enviar desabilitados — lógica de referência: `inputLocked`, `ChatInput.vue:101`); no lugar, chamada para selecionar um template aprovado (ver B3).
- Free entry point 72h ativo conta como janela aberta (o resolver da aria já expõe `free_entry_point.expires_at`; usar o maior dos dois deadlines).

**B2. Backend — guard server-side** (defesa contra UI desatualizada e chamadas diretas):
- **Padrão Estalo (adotar):** service dedicado com método `ensureFreeFormAllowed()` que lança `ValidationException::withMessages(['content' => 'A janela de 24 horas encerrou. Selecione um template aprovado.'])` (ver `WhatsappServiceWindow::ensureFreeFormAllowed`, estalo). Na aria: adicionar método equivalente no `WhatsAppConversationWindowResolver` (ou service novo) e chamar no início de `SendOperatorMessageAction::send` — cobre qualquer caller e o `ValidationException` já vira 422 com bag de erros padrão Inertia. Envio de template (B3) **não** passa por esse guard.
- Aplicar guard só quando o provider da instância é `meta_cloud` (mesmo gate do Estalo: `activeProviderName() !== 'meta_cloud'` → libera) — instâncias não-oficiais não têm janela.
- Caso `unknown` (lead legado sem `service_window_expires_at`): **fallback de leitura** em vez de liberar às cegas — se a coluna for null, computar deadline de `ConversationTimelineMessage` (`direction = 'inbound'`, `MAX(created_at) + 24h`) do lead. É o modelo do Estalo (janela sempre derivada da última inbound, nunca de coluna) aplicado como fallback; leads sem nenhuma inbound ficam `closed` (correto por política Meta — sem inbound não há janela). Implementar dentro do resolver para valer para guard + props + painel lateral de uma vez.

**B3. Enviar template de dentro da conversa (promovido de "opcional" para escopo core — é o desbloqueio real do atendimento):**
Portar o desenho do Estalo, adaptando para a arquitetura da aria (Lead/timeline/outbox em vez de Conversation/Message):

- **Renderer:** portar `app/Whatsapp/WhatsappTemplateRenderer.php` do Estalo **quase verbatim** para `app/Services/WhatsApp/WhatsappTemplateRenderer.php` da aria. Ele resolve tudo que o plano original subestimava: `describe()` gera o manifesto de campos dinâmicos para o form da UI (header texto/mídia, body, botões URL, com exemplos da Meta), `preview()` renderiza com fallback de exemplos, `render()` produz o snapshot imutável do texto enviado, `payload()` monta os components da Cloud API (incl. quick_reply payload e botão URL). Marca componentes não suportados em vez de quebrar. É código testado em produção — não reescrever do zero. Substitui o `TemplateBodyRenderer` proposto na Parte A1 (usar `render()['text']` para o corpo da timeline da campanha também — um renderer só para os dois fluxos).
- **Endpoint:** seguir o Estalo — **mesmo endpoint** de envio (`ConversasController::sendMessage`), não rota nova. `SendConversationMessageRequest` ganha `template_id` (`nullable`, `prohibits:content`, exists em `whatsapp_templates`) e `template_parameters` (array `header|body|buttons`). Validação profunda no `after()` do Form Request, copiando `SendMessageRequest::validateTemplate` do Estalo: template pertence à instância/WABA do lead + status `APPROVED`, `describe()['supported']`, mídia de header presente/ausente/do tipo certo conforme o template, e **dry-run** de `render()` + `payload()` para acusar parâmetro faltante antes de qualquer efeito colateral.
- **Serviço de envio:** novo método em `SendOperatorMessageAction` (ou action irmã `SendOperatorTemplateAction`) espelhando `ConversationMessages::sendHumanTemplate` do Estalo:
  1. valida config/telefone/template sendable;
  2. grava timeline row (`sender_type: 'human'`, `source: 'manual_template'`, `body` = `render()['text']`, `status: 'queued'`) **com snapshot completo em metadata** — template id/nome/idioma/components/parâmetros/rendered (Estalo guarda em `messages.metadata`; a timeline da aria não tem coluna genérica — adicionar migration `metadata json nullable` em `conversation_timeline_messages`, ou reusar `media` — **decisão: coluna nova `metadata`**, `media` tem semântica própria);
  3. envia via `provider->sendTemplate(...)` com components do `payload()`;
  4. `markSent` + `provider_message_id` na timeline, broadcast, `pauseForHuman` + `markHumanResponse` (mesmos side effects do envio manual de texto).
  **Divergência deliberada do Estalo:** o Estalo envia síncrono dentro do request (`dispatchTemplate` faz o HTTP para a Meta na hora; falha vira `failed` e fim). Isso contraria a arquitetura da aria (outbox durável + retry com error tagging). Aqui o envio vai pelo `WhatsappOutboxService` — estender o outbox com payload `type: 'template'` e ensinar `ProcessWhatsappOutboxMessageJob` a despachar via `provider->sendTemplate`. Não copiar o envio in-request.
- **Props da conversa:** `ConversationPanelPropsBuilder` passa a incluir (padrão `AtendimentoController` do Estalo): `whatsappTemplates` (templates da instância do lead, mapeados com `fields` do `describe()`, `preview()`, `sendable`, `status`, `rejectionReason`), `whatsappTemplatesEnabled` (instância meta_cloud com WABA) e `templateSync` (status/erro/última sincronização — a aria já tem sync de templates; expor o estado).
- **UI (3 componentes novos — mesmas funcionalidades do Estalo, visual/textos próprios da aria; usar os componentes e padrões existentes de `conversas/partials`, NÃO copiar o markup do Estalo):**
  - seletor de templates aprovados + ação de sincronizar (dispara o sync existente da aria);
  - dialog/painel de parâmetros com inputs gerados de `template.fields`, preview ao vivo, upload de mídia de header quando exigido;
  - integração no composer (`ConversationThread.vue`): janela fechada → chamada de seleção de template no lugar do input; janela **aberta** → acesso a template também disponível (padrão Estalo: template pode ser enviado a qualquer momento, não só com janela fechada).
- **Fora de escopo desta fase (existe no Estalo, fica para depois):** iniciar atendimento novo por template para contato sem conversa (`EligibleContactsController` + origin `new_atendimento`), edição/deleção de mensagem, cobrança por mensagem.

### Testes (Pest)

- `SendCampaignMessageJob` (feature, com provider fake já usado nos testes de campanha existentes — ver `tests/` por `SendCampaignMessage`):
  - lead existente → timeline row criada com body renderizado, `provider_message_id`, `source='campaign'`;
  - lead inexistente → nenhuma timeline row e nenhum Lead criado;
  - falha na timeline não falha o job.
- `IncomingConversationPersister` backfill: inbound de telefone com CampaignMessage `sent` → lead novo com timeline row do template antes da mensagem inbound (ordem por `created_at`); idempotência (segundo inbound não duplica).
- `ProcessCampaignDeliveryEventJob`: evento `read` atualiza timeline row correlata via `provider_message_id`.
- Guard B2: POST sendMessage com `service_window_expires_at` no passado → 422; no futuro → 200; nulo + inbound na timeline há <24h → 200; nulo + sem inbound → 422; instância não-meta_cloud → 200 sempre.
- `WhatsappTemplateRenderer` (unit): portar/adaptar os testes do Estalo se existirem (`tests/` do Estalo, filtro `TemplateRenderer`); senão cobrir: describe de template com header de mídia + body com 2 params + botão URL; payload com parâmetro faltante lança `InvalidArgumentException`; preview usa exemplos da Meta.
- Envio de template B3 (feature): template aprovado + params válidos → timeline row `manual_template` com body renderizado e metadata snapshot + provider chamado com components corretos; template de outra instância/WABA → 422; param faltante → 422 antes de qualquer side effect; janela fechada **não** bloqueia envio de template.

### Critérios de aceite

1. Disparar campanha para telefone que já tem conversa → mensagem do template aparece na thread em /conversas com hora correta, e o status evolui (entregue/lido) em tempo real.
2. Contato responde a campanha sem conversa prévia → conversa nova já abre mostrando o template enviado antes da resposta.
3. Conversa com janela fechada → composer bloqueado + banner; tentativa via API retorna 422.
4. Conversa com janela aberta → envio manual funciona como hoje (zero regressão).

---

## Ajuste 3 — Importação de listas: aliases de coluna de telefone

### Sintoma

Import de CSV/TXT exige coluna chamada exatamente `TELEFONE` (ou `phone`). Erro atual: `"Coluna \"TELEFONE\" não encontrada. Verifique o padrão da planilha."` Usuários chegam com planilhas usando `celular`, `whatsapp`, `numero`, `contato` etc.

### Causa raiz

[ContactListCsvImporter.php:57-62](../app/Services/ContactListCsvImporter.php) — busca exata por `telefone|phone`, `telefone2|phone2`, `nome|name` após lowercase+trim.

### Implementação

1. **Normalização de header:** além de lowercase/trim/BOM já existentes, remover acentos (`Str::ascii`) e colapsar separadores (`[\s_\-\.]+` → vazio) antes do match. Assim `Número  Whatsapp`, `numero_whatsapp`, `NUMERO-WHATSAPP` convergem para `numerowhatsapp`.

2. **Tabelas de alias** (constantes da classe, match exato pós-normalização, primeira coluna que casar vence, prioridade da esquerda para direita):

```php
private const PHONE_HEADER_ALIASES = [
    'telefone', 'telefone1', 'telefoneprincipal', 'tel', 'tel1',
    'celular', 'celular1', 'cel', 'whatsapp', 'wpp', 'zap',
    'fone', 'numero', 'numerowhatsapp', 'numerotelefone', 'numerocelular',
    'phone', 'phonenumber', 'mobile', 'contato',
];

private const PHONE2_HEADER_ALIASES = [
    'telefone2', 'tel2', 'celular2', 'whatsapp2', 'numero2', 'phone2', 'telefonesecundario',
];

private const NAME_HEADER_ALIASES = [
    'nome', 'nomecompleto', 'name', 'fullname', 'cliente', 'nomecliente', 'razaosocial',
];
```

Regras de resolução:
- `contato` fica **por último** na lista de phone (ambíguo — pode ser nome); só é usado se nenhum outro alias de telefone existir. Se `contato` for eleito como telefone E também não houver coluna de nome, não usar `contato` como nome.
- Coluna eleita como phone não pode ser reeleita como phone2/nome (índices distintos — o código atual já assume isso via `$phoneCol !== $phone2Col`).
- Colunas não mapeadas continuam indo para `extra_data` com o header original (não normalizado) — comportamento atual preservado (headers de `extra_data` são referenciados pelo mapping de params de campanha; **não** mudar a chave usada em `extra_data`).

3. **Fallback headerless (opcional, recomendado):** se nenhum alias casar, testar se o próprio header row é um telefone válido (`ContactSyncService::normalizePhone($headers[0]) !== null`). Se sim, tratar arquivo como sem cabeçalho: coluna 0 = telefone, reprocessar o header row como primeira linha de dados. Cobre o caso comum de `.txt` com um número por linha.

4. **Mensagem de erro atualizada:** `"Nenhuma coluna de telefone encontrada. Use um cabeçalho como TELEFONE, CELULAR, WHATSAPP ou NUMERO."`

5. **Frontend (menor):** em `listas-contato/Show.vue` (ou onde fica o upload), atualizar texto de ajuda que mencione o padrão `TELEFONE` obrigatório, se existir.

6. **Fora de escopo (registrar):** suporte a `.xlsx` exigiria dependência nova (ex. `maatwebsite/excel` ou `openspout`) — requer aprovação de dependência; não incluir neste slice. Validação `mimes:csv,txt` em `ImportContactListCsvRequest` permanece.

### Testes (Pest)

Localizar testes existentes do importer (`php artisan test --compact --filter=ContactListCsvImporter` ou grep em `tests/` por `importCsv`) e adicionar datasets:
- header `CELULAR` → importa;
- header `Número WhatsApp` (com acento e espaço) → importa;
- header `contato` sozinho → usado como telefone; `contato` + `celular` → `celular` vence e `contato` vai para `extra_data`;
- `telefone2` alias (`celular2`) → segundo telefone importado;
- arquivo `.txt` sem header, um número por linha → importa (se fallback implementado);
- nenhum alias → erro com a mensagem nova;
- regressão: `TELEFONE`/`phone` continuam funcionando e `extra_data` mantém chaves originais.

### Critério de aceite

Planilha real com coluna `CELULAR` ou `WHATSAPP` importa sem edição manual do cabeçalho; dedup e normalização de telefone inalterados.

---

## Ordem de execução sugerida

1. **Ajuste 3** (isolado, backend puro, destrava validação com listas reais).
2. **Ajuste 1** (rápido, frontend puro).
3. **Renderer** (base compartilhada): portar `WhatsappTemplateRenderer` do Estalo + unit tests — pré-requisito de A e B3.
4. **Ajuste 2 Parte A** (template de campanha na timeline): A2 → A4 → A3, commits separados.
5. **Ajuste 2 Parte B1 + B2** (trava de janela: UI + guard).
6. **Ajuste 2 Parte B3** (envio de template na conversa): backend (request + action + props) → UI (dropdown + dialog + integração no composer). Maior sub-escopo, mas o desenho está resolvido — é porte do Estalo adaptado a Lead/timeline.

## Riscos e pontos de atenção

- **Formato de telefone Lead × CampaignMessage (A2):** `Lead.whatsapp` vem do webhook (wa_id Meta, E.164 sem `+`); `$destination` vem de `PhoneNumberValidator::normalize`. Confirmar equivalência com dados reais antes de fechar o predicado de lookup; caso divirjam (ex. 9º dígito BR), usar `ContactSyncService::normalizePhone` dos dois lados como chave de comparação.
- **Volume (A2):** 1 query extra de lookup de Lead por mensagem enviada no fan-out. Aceitável (o job já faz várias queries por mensagem); não fazer eager create de Lead em hipótese alguma.
- **`unknown` window (B2):** decisão conservadora documentada acima — bloquear apenas `closed` explícito. Revisitar quando todos os leads ativos tiverem `service_window_expires_at` populado.
- **Sessões concorrentes no checkout:** este repositório é compartilhado por sessões concorrentes — antes de commitar, `git status` e escopo de `git add` restrito aos arquivos do slice.
- **Deploy:** após merge, `main` é a branch de deploy (deploy.sh puxa de main).

## Arquivos-chave (mapa para a sessão de implementação)

| Área | Arquivo |
|------|---------|
| Tela campanha (datas) | `resources/js/pages/campanhas/Show.vue` (linhas ~743-749) |
| Utilitário de data (novo) | `resources/js/lib/datetime.ts` |
| Envio de campanha | `app/Jobs/SendCampaignMessageJob.php` |
| Eventos de entrega | `app/Jobs/ProcessCampaignDeliveryEventJob.php` |
| Timeline | `app/Services/ConversationTimelineService.php`, `app/Models/ConversationTimelineMessage.php` |
| Criação de Lead inbound | `app/Services/IncomingConversationPersister.php` |
| Janela 24h (resolver) | `app/Services/WhatsApp/WhatsAppConversationWindowResolver.php` |
| Props da conversa | `app/Services/ConversationPanelPropsBuilder.php` |
| Envio manual | `app/Http/Controllers/ConversasController.php::sendMessage`, `app/Actions/SendOperatorMessageAction.php` |
| Thread UI | `resources/js/pages/conversas/partials/ConversationThread.vue`, `resources/js/pages/conversas/types.ts` |
| Import de lista | `app/Services/ContactListCsvImporter.php`, `app/Http/Requests/ImportContactListCsvRequest.php` |

## Mapa Estalo → Aria (referência de porte para B/B3)

Implementação de referência em `D:\DOCS\PROMOSYS\Estalo_hub\estalo` (mesmo autor, produção). Consultar durante a implementação; adaptar nomenclatura de domínio (`Conversation/Contact/PromotoraConfiguration` → `Lead/Contact/WhatsappInstance`).

| Peça | Estalo (fonte) | Aria (destino) | Adaptação |
|------|----------------|----------------|-----------|
| Renderer de template | `app/Whatsapp/WhatsappTemplateRenderer.php` | `app/Services/WhatsApp/WhatsappTemplateRenderer.php` (novo) | Porte quase verbatim; só namespace |
| Janela 24h (cálculo) | `app/Services/WhatsappServiceWindow.php` (deriva de MAX inbound `sent_at` + 24h; `ensureFreeFormAllowed` lança ValidationException) | `app/Services/WhatsApp/WhatsAppConversationWindowResolver.php` (existente) | Manter coluna `service_window_expires_at` como fonte primária; adicionar fallback por timeline + método `ensureFreeFormAllowed` |
| Endpoint de envio unificado | `app/Http/Controllers/Atendimento/SendMessageController.php` (texto \| mídia \| template no mesmo POST, `prohibits`) | `ConversasController::sendMessage` + `SendConversationMessageRequest` | Adicionar branch `template_id` |
| Validação profunda de template | `app/Http/Requests/Atendimento/SendMessageRequest.php::validateTemplate` (sendable + describe + mídia de header + dry-run render/payload) | `SendConversationMessageRequest::after()` | Trocar checagem de WABA por instância do lead |
| Persistência com snapshot | `app/Services/ConversationMessages.php::sendHumanTemplate` (content = texto renderizado; `metadata.whatsapp_template` = id/nome/idioma/components/params/rendered) | `SendOperatorMessageAction` (ou action irmã) + migration `metadata` em `conversation_timeline_messages` | Timeline em vez de `messages`; side effects aria (pauseForHuman, ticket) |
| Envio provider | `ConversationMessages::dispatchTemplate` → `WhatsappService::sendTemplate` (síncrono in-request) | `WhatsappOutboxService` + `ProcessWhatsappOutboxMessageJob` → `provider->sendTemplate` | **Não copiar o síncrono** — usar outbox durável da aria |
| Elegibilidade de template | `WhatsappService::templateSendable` (WABA match + `isSendable`) | Checagem instância do lead + `status === 'APPROVED'` | Simplificar |
| Composer travado + banner | `resources/js/pages/Atendimento/components/ChatInput.vue` (`inputLocked`, bloco linhas 455-487) | `resources/js/pages/conversas/partials/ConversationThread.vue` | Referência de **comportamento** apenas — visual/textos próprios da aria |
| Dropdown de templates + sync | `.../components/WhatsappTemplateDropdown.vue` | novo partial em `conversas/partials/` | Mesma funcionalidade, markup próprio; ligar ao sync existente da aria |
| Dialog de parâmetros | `.../components/WhatsappTemplateSendDialog.vue` (form dinâmico de `template.fields` + preview) | novo partial em `conversas/partials/` | Mesma funcionalidade, markup próprio; `fields` vem do `describe()` via props |
| Props de templates | `app/Http/Controllers/AtendimentoController.php` (linhas ~87-187: templates mapeados com fields/preview/sendable, `serviceWindows` com deadline ISO) | `app/Services/ConversationPanelPropsBuilder.php` | Incluir `whatsappTemplates`, `whatsappTemplatesEnabled`, `templateSync` |
| Janela reativa no client | `resources/js/pages/Atendimento/Index.vue:833` (inbound novo recalcula deadline local) | listener `NewConversationMessage` na página conversas | Mesmo padrão |

### Avaliado no Estalo e deliberadamente NÃO adotado

- **Envio síncrono in-request** — contraria outbox durável + retry da aria (detalhe em B3).
- **Janela 100% derivada de query** (`MAX(inbound)+24h` com join por request) — aria mantém `service_window_expires_at` como fonte primária (mais barato, tempo real); derivação vira só fallback para lead legado.
- **Prefixo `*Nome:*` do atendente no texto** (`humanWireText`) — decisão de produto do Estalo, não requisito da aria.
- **Billing por mensagem** (`chargeForHumanDelivery`) — sem equivalente na aria.
- **Edição/deleção de mensagem via provider** — fora de escopo.
- **Cópia do frontend** — proibida por decisão do produto: mesmas funcionalidades e avisos, apresentação própria.
