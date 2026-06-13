# Plano exclusivo: gestao profissional de atendimentos, status do lead e handoff humano

Data: 2026-05-10

## Status da implementacao

### 2026-05-10 - Fase 0 iniciada e concluida

- Padronizado `service_tickets.status` para valores canonicos `open`, `resolved`, `closed`.
- Mantida compatibilidade com URLs e dados antigos usando aliases `aberto`, `resolvido`, `fechado`.
- Criada migration para normalizar registros existentes.
- Atualizados controller, middleware, tools da IA, job de retry, UI de filtros e labels de ticket.
- Adicionados testes para filtro canonico e filtro legado `aberto`.
- Validacao executada: `php artisan test tests\Feature\ServiceTicketControllerTest.php tests\Feature\AuthorizationPolicyTest.php tests\Feature\MultiTenancyTest.php`.

## Objetivo

Transformar `/atendimentos` de uma lista simples de tickets em uma operacao comercial confiavel, com funil visual estilo kanban, automacao por IA, SLA, escalamento humano rastreavel e controle claro entre:

- status comercial do lead;
- estado operacional da conversa;
- ticket de atendimento humano;
- follow-up automatico;
- intervencao manual a qualquer momento.

Fluxo alvo:

campanhas Meta HSM e URA -> qualificacao e venda automatica pela IA -> follow-up automatico quando o cliente nao responde -> escalamento para atendente humano -> intervencao humana a qualquer momento -> encerramento/conversao/perda com motivo.

## Diagnostico do codigo atual

### O que existe hoje

- `/atendimentos` usa `ServiceTicketController::index` e renderiza `resources/js/pages/atendimentos/Index.vue`.
- A tela lista `service_tickets` com filtros por status, tipo, motivo/resumo e data.
- `EscalarParaHumanoTool` cria `ServiceTicket` com `type = escalation`, muda `leads.status` para `escalado` e desativa `followup_status`.
- `RegistrarLeadSemCreditoTool` cria `ServiceTicket` com `type = no_credit`, muda `leads.status` para `sem_credito` e desativa follow-up.
- A tela de conversas ja possui pausa manual da IA via `PauseService`.
- A timeline unificada (`conversation_timeline_messages`) e a outbox (`whatsapp_outbox_messages`) ja permitem auditar mensagens de IA, humano, follow-up, campanha e webhook.
- `leads.status` ja tem maquina basica: `novo`, `qualificado`, `sem_credito`, `desqualificado`, `escalado`, `convertido`, `optou_sair`.
- `leads.followup_status` controla motor de follow-up: `inactive`, `active`, `paused`.

### Problemas encontrados

1. Inconsistencia critica de status de ticket.
   - Migration original usa `service_tickets.status` default `open`.
   - Tools criam tickets com `status = open`.
   - UI, filtros e testes esperam `aberto`, `resolvido`, `fechado`.
   - Resultado provavel: `/atendimentos?status=aberto` pode nao mostrar tickets reais criados pela IA.

2. `/atendimentos` nao e uma central de atendimento completa.
   - Hoje e uma lista paginada de tickets.
   - Nao ha atribuicao de atendente.
   - Nao ha SLA persistido.
   - Nao ha transicoes operacionais de ticket pela UI.
   - Nao ha coluna visual de funil.
   - Nao ha fila de "precisa responder agora".

3. `leads.status` mistura funil comercial com estado operacional.
   - `escalado` indica handoff humano, mas nao diz se esta aguardando atendente, em atendimento, resolvido ou abandonado.
   - `qualificado` indica oportunidade, mas nao diz se esta com IA ativa, em follow-up, aguardando documento ou aguardando humano.

4. Intervencao humana e cache, nao estado auditavel.
   - `PauseService` pausa a IA por telefone e tenant com TTL de 10h.
   - Isso e util para seguranca imediata, mas nao serve como fonte de verdade para gestao, auditoria, SLA ou kanban.

5. Handoff humano nao tem ownership.
   - Ticket nao possui `assigned_user_id`.
   - Nao existe `claimed_at`, `first_response_at`, `resolved_at`, `closed_at`.
   - Nao existe prioridade persistida.
   - Nao existe motivo padronizado de fechamento.

6. Falta painel de confiabilidade operacional.
   - Nao ha alertas por ticket parado.
   - Nao ha contagem por etapa do funil.
   - Nao ha metrica de tempo ate primeiro atendimento humano.
   - Nao ha taxa de automacao versus intervencao humana por campanha/agente.

## Decisao de produto

Criar uma nova experiencia em `/atendimentos` com duas visoes:

1. Kanban operacional automatizado.
2. Lista/tabela analitica para busca, filtros e auditoria.

O kanban deve ser familiar para vendedores, mas nao deve depender de drag-and-drop manual como fonte principal. A IA e os eventos do sistema movem cards automaticamente. O humano pode intervir, assumir, pausar IA, responder e encerrar.

## Modelo mental correto

Separar tres eixos:

### 1. Status comercial do lead

Fonte: `leads.status`.

Uso: resultado de negocio.

Estados recomendados:

- `novo`
- `qualificado`
- `sem_credito`
- `desqualificado`
- `escalado`
- `convertido`
- `optou_sair`

Nao usar `leads.status` para representar detalhes como "em atendimento humano" ou "aguardando resposta". Isso deve ficar em estado operacional.

### 2. Estado operacional da conversa

Nova fonte recomendada: `lead_pipeline_states` ou colunas dedicadas em `leads`.

Uso: kanban e orquestracao.

Estados recomendados:

- `new_inbound`: chegou e ainda nao houve resposta util.
- `ai_qualifying`: IA esta qualificando.
- `qualified_opportunity`: lead qualificado com oportunidade aberta.
- `ai_followup`: follow-up automatico ativo.
- `human_pending`: escalado e aguardando humano assumir.
- `human_active`: humano assumiu e IA pausada.
- `proposal_sent`: proposta/documentos em andamento.
- `won`: convertido.
- `future_opportunity`: sem credito ou sem margem, contato futuro.
- `lost`: desqualificado, opt-out ou perda.

### 3. Ticket de atendimento humano

Fonte: `service_tickets`.

Uso: trabalho humano, SLA, ownership e fechamento.

Estados recomendados:

- `open`: criado e aguardando acao.
- `assigned`: atendente assumiu.
- `waiting_customer`: atendente respondeu e aguarda cliente.
- `waiting_internal`: depende de analise/documento/integracao.
- `resolved`: resolvido operacionalmente.
- `closed`: fechado sem nova acao.

Manter status internos em ingles por consistencia tecnica e traduzir na UI. Se quiser manter portugues, padronizar tudo em portugues. O mais importante e nao misturar `open` com `aberto`.

## Kanban proposto

### Colunas

1. Entrada
   - Leads novos, resposta recente do cliente, sem etapa clara.
   - Origem: webhook, campanha HSM, URA, importacao.

2. IA qualificando
   - IA ativa, coletando CPF/documentos ou consultando credito.
   - Mostra agente responsavel, ultima mensagem e tempo desde ultima interacao.

3. Oportunidade qualificada
   - `leads.status = qualificado`.
   - Credito disponivel ou produto recomendado.
   - Pode estar aguardando cliente ou IA.

4. Follow-up automatico
   - `leads.followup_status = active`.
   - Mostra tentativa atual, proximo envio e ultima resposta do cliente.

5. Escalado para humano
   - Ticket `open` ou `human_pending`.
   - Prioridade por SLA, motivo de escalamento e resumo da IA.

6. Em atendimento humano
   - Ticket `assigned` ou conversa em pausa humana auditavel.
   - Mostra atendente, tempo de posse, ultima mensagem humana e proximo passo.

7. Proposta/documentos
   - Cliente aceitou produto ou esta em coleta/validacao de documentos.
   - Pode ser derivado de ticket reason `proposta_aceita` ou novo estado operacional.

8. Ganhos
   - `leads.status = convertido`.

9. Futuro/sem credito
   - `leads.status = sem_credito`.
   - Nao deve competir com atendimento urgente, mas deve ter fila de reativacao futura.

10. Perdidos/opt-out
   - `desqualificado` e `optou_sair`.
   - Exibicao filtravel, nao necessariamente coluna padrao.

### Card do kanban

Cada card deve mostrar, no minimo:

- nome/telefone;
- origem: Meta HSM, URA, organico, follow-up, manual;
- agente IA;
- status comercial;
- estado operacional;
- ticket aberto, se existir;
- responsavel humano, se existir;
- ultima mensagem resumida;
- tempo desde ultima interacao;
- SLA restante ou atrasado;
- motivo do escalamento;
- valor/produto quando houver;
- badges: follow-up ativo, IA pausada, opt-out, erro de envio, aguardando cliente.

### Acoes rapidas no card

- Abrir conversa.
- Assumir atendimento.
- Pausar/retomar IA.
- Enviar mensagem.
- Marcar como aguardando cliente.
- Marcar proposta/documentos.
- Resolver ticket.
- Fechar como convertido.
- Fechar como perdido.
- Reencaminhar para outro atendente.

## Regras automaticas de movimentacao

### Entrada -> IA qualificando

Quando:

- mensagem inbound registrada;
- IA gerou resposta ou iniciou consulta;
- lead ainda nao e terminal.

Acao:

- `operational_stage = ai_qualifying`.

### IA qualificando -> Oportunidade qualificada

Quando:

- tool de credito retorna `QUALIFICADO`;
- `leads.status = qualificado`.

Acao:

- ativar follow-up se configurado;
- registrar evento `lead_qualified`;
- mover para `qualified_opportunity`.

### Oportunidade qualificada -> Follow-up automatico

Quando:

- lead qualificado sem resposta dentro da janela configurada;
- `followup_status = active`.

Acao:

- mover para `ai_followup`;
- mostrar proxima tentativa.

### Qualquer etapa -> Escalado para humano

Quando:

- IA chama `EscalarParaHumanoTool`;
- cliente pede humano;
- fact-check falha fechado;
- ferramenta externa falha de forma critica;
- operador clica em "Escalar";
- sentimento/risco operacional ultrapassa limite.

Acao:

- criar ou reutilizar ticket aberto idempotente;
- `leads.status = escalado`;
- `followup_status = inactive`;
- pausar IA com estado persistente;
- `operational_stage = human_pending`;
- registrar timeline/evento.

### Escalado para humano -> Em atendimento humano

Quando:

- atendente clica "Assumir";
- operador envia mensagem manual;
- conversa esta pausada manualmente com ticket aberto.

Acao:

- preencher `assigned_user_id`;
- preencher `claimed_at`;
- `ticket.status = assigned`;
- `operational_stage = human_active`;
- IA permanece pausada.

### Em atendimento humano -> Proposta/documentos

Quando:

- atendente marca proposta enviada;
- IA/ticket reason indica `proposta_aceita`;
- documentos pendentes aparecem.

Acao:

- `operational_stage = proposal_sent`;
- registrar proximo passo e SLA.

### Qualquer etapa -> Ganhos

Quando:

- operador marca convertido;
- integracao externa confirma conversao;
- regra comercial finaliza venda.

Acao:

- `leads.status = convertido`;
- fechar tickets abertos como `resolved`;
- `followup_status = inactive`;
- `operational_stage = won`.

### Qualquer etapa -> Perdidos/opt-out

Quando:

- cliente pede opt-out;
- operador marca perda;
- IA classifica recusa explicita e ferramenta atualiza status.

Acao:

- `leads.status = optou_sair` ou `desqualificado`;
- fechar ticket aberto;
- `followup_status = inactive`;
- bloquear novos envios automaticos;
- `operational_stage = lost`.

## Mudancas de schema recomendadas

### Hotfix imediato

Padronizar `service_tickets.status`.

Opcao recomendada:

- migrar `aberto` -> `open`;
- migrar `resolvido` -> `resolved`;
- migrar `fechado` -> `closed`;
- alterar UI para traduzir labels;
- alterar testes para status canonico em ingles.

Opcao alternativa:

- migrar `open` -> `aberto`;
- manter UI atual;
- alterar tools para criarem `aberto`.

Recomendacao tecnica: usar ingles nos valores canonicos e portugues apenas na UI.

### `service_tickets`

Adicionar:

- `assigned_user_id` nullable;
- `priority` (`low`, `normal`, `high`, `urgent`);
- `sla_due_at` nullable;
- `claimed_at` nullable;
- `first_response_at` nullable;
- `resolved_at` nullable;
- `closed_at` nullable;
- `resolution_reason` nullable;
- `resolution_notes` nullable;
- `last_customer_message_at` nullable;
- `last_operator_message_at` nullable;
- `metadata` JSON nullable.

Indices:

- `(tenant_id, status, priority, sla_due_at)`;
- `(tenant_id, assigned_user_id, status)`;
- `(tenant_id, lead_id, status)`.

### Estado operacional

Criar tabela `lead_pipeline_states`:

- `id`;
- `tenant_id`;
- `lead_id` unique;
- `stage`;
- `stage_reason` nullable;
- `source` (`ai`, `human`, `system`, `webhook`, `followup`, `campaign`, `ura`);
- `confidence` nullable;
- `assigned_user_id` nullable;
- `active_ticket_id` nullable;
- `last_stage_changed_at`;
- `next_action_at` nullable;
- `sla_due_at` nullable;
- `metadata` JSON nullable;
- timestamps.

Criar tabela `lead_pipeline_stage_events`:

- `id`;
- `tenant_id`;
- `lead_id`;
- `from_stage` nullable;
- `to_stage`;
- `source`;
- `actor_type` (`ai`, `human`, `system`);
- `actor_id` nullable;
- `reason` nullable;
- `interaction_id` nullable;
- `service_ticket_id` nullable;
- `metadata` JSON nullable;
- timestamps.

Motivo:

- `lead_pipeline_states` entrega leitura rapida para kanban.
- `lead_pipeline_stage_events` entrega auditoria e depuracao.

### Estado persistente da IA

Criar estado auditavel para pausa/intervencao, sem depender apenas de cache:

Opcao simples:

- adicionar em `lead_pipeline_states.metadata`: `ai_paused`, `ai_pause_reason`, `ai_paused_by`, `ai_paused_until`.

Opcao melhor:

- tabela `lead_ai_control_states` com `lead_id`, `mode`, `paused_until`, `reason`, `paused_by`, `updated_by`.

Estados sugeridos:

- `ai_active`;
- `human_override`;
- `paused_by_system`;
- `paused_by_operator`;
- `terminal`.

`PauseService` pode continuar existindo como cache de execucao, mas deve espelhar o estado persistido.

## Backend: servicos e controllers

### `LeadPipelineService`

Responsavel por:

- calcular etapa atual;
- mover etapa com idempotencia;
- registrar evento;
- sincronizar com lead, ticket, follow-up e pausa da IA;
- emitir broadcast para kanban.

Metodos:

- `syncFromLead(Lead $lead, string $source, ?string $interactionId = null)`;
- `moveTo(Lead $lead, string $stage, array $context = [])`;
- `escalateToHuman(Lead $lead, array $context = [])`;
- `claimTicket(ServiceTicket $ticket, User $user)`;
- `resolveTicket(ServiceTicket $ticket, User $user, array $data)`;
- `closeAsWon(Lead $lead, User $user, array $data)`;
- `closeAsLost(Lead $lead, User $user, array $data)`.

### `ServiceTicketLifecycleService`

Responsavel por:

- criar ticket idempotente por lead/tipo/status aberto;
- atribuir atendente;
- calcular SLA;
- fechar/resolver;
- atualizar timestamps.

Regra importante:

- nao criar varios tickets abertos iguais para o mesmo lead e tipo;
- se ja existe ticket aberto, atualizar resumo/metadados e reutilizar.

### Rotas novas

Adicionar:

- `GET /atendimentos` com query `view=kanban|list`.
- `GET /atendimentos/kanban` ou prop Inertia dedicada.
- `POST /atendimentos/{ticket}/claim`.
- `PATCH /atendimentos/{ticket}` para status, prioridade, observacoes e responsavel.
- `POST /atendimentos/{ticket}/resolve`.
- `POST /atendimentos/{ticket}/close`.
- `POST /atendimentos/leads/{lead}/escalate`.
- `POST /atendimentos/leads/{lead}/stage`.
- `POST /atendimentos/leads/{lead}/convert`.
- `POST /atendimentos/leads/{lead}/lost`.

### Eventos e realtime

Adicionar broadcast:

- `LeadPipelineStageChanged`;
- `ServiceTicketAssigned`;
- `ServiceTicketSlaBreached`;
- `HumanHandoffCreated`.

Kanban deve atualizar sem refresh quando:

- lead muda status;
- ticket e criado;
- ticket e assumido;
- nova mensagem chega;
- operador envia mensagem;
- follow-up ativa/desativa.

## Frontend: experiencia proposta

### Nova tela `/atendimentos`

Layout:

- topo com busca global e filtros compactos;
- tabs: `Kanban`, `Lista`, `SLA`, `Sem credito`;
- metricas pequenas: abertos, atrasados, aguardando humano, em atendimento, convertidos hoje;
- area principal com colunas kanban;
- drawer lateral para detalhes do card sem sair da tela;
- link direto para conversa completa.

### Filtros

- agente IA;
- atendente humano;
- origem: Meta, URA, organico, follow-up, manual;
- status comercial;
- etapa operacional;
- prioridade;
- SLA: atrasado, vence hoje, sem SLA;
- tipo de ticket;
- campanha;
- busca por nome, telefone, CPF, resumo.

### Drawer do card

Conteudo:

- dados do lead;
- timeline resumida;
- resumo da IA;
- credito/produto;
- ticket aberto;
- SLA;
- responsavel;
- acoes rapidas.

Acoes:

- assumir;
- responder;
- pausar/retomar IA;
- alterar prioridade;
- mudar etapa;
- resolver;
- converter;
- perder/opt-out.

### Lista analitica

Manter uma tabela similar a atual, mas com:

- status canonico correto;
- coluna responsavel;
- SLA;
- prioridade;
- etapa;
- origem;
- ultima mensagem;
- acoes de lifecycle.

## IA: melhorias no escalamento

### `EscalarParaHumanoTool`

Melhorar schema para:

- `motivo_codigo`: `proposta_aceita`, `solicitacao_cliente`, `problema_tecnico`, `risco_compliance`, `falha_ferramenta`, `documentos_completos`, `outro`;
- `urgencia`: `low`, `normal`, `high`, `urgent`;
- `resumo_operacional`;
- `proximo_passo_recomendado`;
- `produto_escolhido`;
- `valor_total`;
- `valor_parcela`;
- `documentos_pendentes`;
- `confidence`.

Comportamento:

- idempotente por lead e tipo;
- cria/atualiza ticket;
- move pipeline para `human_pending`;
- pausa IA de forma persistente;
- registra evento com `interaction_id`.

### Classificador de etapa

Criar `LeadPipelineClassifier` com regras deterministicas primeiro:

- `status = convertido` -> `won`;
- `status = optou_sair/desqualificado` -> `lost`;
- ticket aberto sem responsavel -> `human_pending`;
- ticket aberto com responsavel -> `human_active`;
- `followup_status = active` -> `ai_followup`;
- `status = qualificado` -> `qualified_opportunity`;
- caso contrario -> `ai_qualifying` ou `new_inbound`.

LLM pode sugerir `stage_reason` e `next_action`, mas nao deve sobrescrever regra critica sem validacao.

## SLA e prioridade

### Prioridade automatica

`urgent`:

- cliente pediu humano explicitamente;
- proposta aceita;
- erro/falha critica;
- ticket atrasado.

`high`:

- lead qualificado com valor alto;
- documentos completos;
- cliente respondeu depois de follow-up.

`normal`:

- escalamento comum.

`low`:

- sem credito/futuro.

### SLA inicial recomendado

- urgente: 15 minutos;
- alto: 1 hora;
- normal: 4 horas;
- baixo: 24 horas;
- sem credito/futuro: sem SLA ou 7 dias.

### Metricas

- tempo medio ate humano assumir;
- tempo medio ate primeira resposta humana;
- tickets atrasados;
- conversao por atendente;
- conversao por agente IA;
- taxa de escalamento por campanha;
- motivos de escalamento;
- taxa de follow-up que vira resposta;
- taxa de leads que precisaram de humano.

## Fases de implementacao

### Fase 0: hotfix e consistencia

Objetivo: corrigir bugs que afetam confiabilidade imediatamente.

Entregas:

- padronizar `service_tickets.status`;
- migrar dados existentes;
- ajustar `EscalarParaHumanoTool` e `RegistrarLeadSemCreditoTool`;
- ajustar `ticket-status.ts`;
- ajustar filtros de `/atendimentos`;
- adicionar testes cobrindo ticket criado pela IA aparecendo em `/atendimentos?status=open` ou equivalente canonico;
- corrigir textos com encoding quebrado na tela atual.

Aceite:

- todo ticket criado por IA aparece na tela de atendimentos com filtro de abertos;
- contador de abertos bate com os cards exibidos;
- nenhum status canonico divergente entre backend, frontend e testes.

### Fase 1: lifecycle de ticket humano

Objetivo: tornar atendimento humano profissional.

Entregas:

- adicionar campos de responsavel, prioridade e SLA;
- criar `ServiceTicketLifecycleService`;
- rotas para assumir, resolver e fechar;
- UI de acoes na lista atual;
- primeira versao de SLA visual;
- ticket idempotente por lead/tipo aberto.

Aceite:

- atendente consegue assumir ticket;
- sistema registra `claimed_at`;
- primeira mensagem humana registra `first_response_at`;
- ticket pode ser resolvido/fechado com motivo;
- IA fica pausada enquanto atendimento humano estiver ativo.

### Fase 2: estado operacional persistente

Objetivo: separar funil comercial de etapa operacional.

Entregas:

- criar `lead_pipeline_states`;
- criar `lead_pipeline_stage_events`;
- criar `LeadPipelineService`;
- sincronizar eventos de inbound, qualificacao, follow-up, escalamento, pausa humana, resolucao e conversao;
- backfill para leads existentes;
- filtros por etapa operacional.

Aceite:

- cada lead tem exatamente uma etapa operacional atual;
- mudancas importantes geram evento auditavel;
- etapa operacional pode ser reconstruida por eventos;
- lead escalado aparece em `human_pending` ou `human_active`, nao apenas como `escalado`.

### Fase 3: kanban automatizado

Objetivo: entregar a UI que vendedores reconhecem e conseguem operar.

Entregas:

- tab Kanban em `/atendimentos`;
- colunas configuradas;
- cards com dados principais;
- drawer de detalhes;
- acoes rapidas;
- atualizacao por broadcast;
- filtros principais.

Aceite:

- vendedor enxerga filas e prioridades sem abrir cada conversa;
- card muda de coluna automaticamente apos evento de IA/humano/follow-up;
- atendente consegue assumir e responder a partir do fluxo;
- layout funciona em desktop e notebook comum.

### Fase 4: automacao inteligente e governanca

Objetivo: usar IA para organizar a operacao sem perder controle.

Entregas:

- classificador de prioridade e proximo passo;
- resumo operacional do handoff;
- sugestao de etapa, sem sobrescrever regras criticas;
- alertas de SLA;
- dashboard de atendimento;
- metricas por campanha, agente e atendente.

Aceite:

- gestor sabe onde a operacao esta travada;
- tickets urgentes sobem automaticamente;
- follow-up e humano nao brigam pelo mesmo lead;
- queda de integracao/fact-check gera fila humana clara.

## Regras de confiabilidade

- Toda mudanca de etapa deve ser idempotente.
- Toda criacao de ticket por IA deve ser transacional com mudanca de lead e pipeline.
- Toda intervencao humana deve pausar IA de forma persistente.
- Toda retomada de IA deve exigir que nao exista ticket humano ativo.
- Opt-out deve ser terminal e bloquear campanhas/follow-up/IA.
- Follow-up nao deve enviar mensagem para lead em atendimento humano.
- Ticket aberto duplicado para mesmo lead/tipo deve ser evitado.
- Kanban deve ser derivado do banco, nao apenas do estado local do frontend.
- Cache pode acelerar, mas nao pode ser fonte de verdade operacional.

## Testes minimos

Backend:

- tool de escalamento cria ticket canonico e move pipeline;
- ticket criado por IA aparece no filtro de abertos;
- assumir ticket preenche responsavel e `claimed_at`;
- primeira mensagem humana preenche `first_response_at`;
- resolver ticket fecha SLA e move etapa corretamente;
- follow-up ignora lead com ticket humano ativo;
- opt-out fecha ticket e bloqueia envios;
- backfill cria pipeline state para leads existentes;
- eventos de pipeline sao registrados com `interaction_id`.

Frontend:

- kanban renderiza colunas e cards;
- filtro por atendente/agente/origem/status funciona;
- acao "Assumir" move card para atendimento humano;
- card atrasado mostra indicador de SLA;
- drawer mostra resumo, ticket e ultimas mensagens;
- lista continua funcionando para auditoria.

## Ordem recomendada de execucao

1. Corrigir status de ticket.
2. Criar lifecycle humano minimo.
3. Persistir estado operacional do lead.
4. Construir kanban usando estado persistido.
5. Automatizar prioridade/SLA/proximo passo.
6. Adicionar metricas gerenciais.

## Resultado esperado

Ao final, `/atendimentos` deixa de ser uma lista reativa de excecoes e vira uma central de operacao comercial:

- IA organiza e move os leads automaticamente;
- vendedores trabalham em uma UI familiar de kanban;
- gestores enxergam gargalos e SLA;
- clientes nao recebem follow-up indevido durante atendimento humano;
- cada handoff tem resumo, responsavel, prioridade e trilha de auditoria;
- a plataforma fica preparada para escalar campanhas Meta HSM, URA e prospeccao sem perder controle operacional.
