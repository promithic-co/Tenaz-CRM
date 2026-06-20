# Plano de Robustez Operacional do Agente IA

Este documento descreve um plano executavel para reduzir risco operacional, melhorar manutencao, acelerar diagnostico de bugs e preparar o Aria para rodar prospeccao em escala com WhatsApp oficial, campanhas, follow-up, consultas externas, creditos por tenant e paineis de laboratorio mais uteis.

O objetivo nao e trocar a arquitetura atual. A base esta boa: existe `AgentService`, middleware de auditoria/token, ferramentas com regras de negocio, `FactCheckService`, `DataFidelityValidator`, stress tests e dashboard em `/laboratory`. O plano abaixo organiza a evolucao em camadas para transformar isso em um sistema previsivel, auditavel e facil de operar.

Você deverá manter o estado do plano atualizado no arquivo FASE PLANO agent-operational-hardening.md com este template:

FASES CONCLUIDAS: X.X
FASE ATUAL (em andamento?): X.X (s/n)  
PRÓXIMA FASE: X.X

## Contexto Atual

Hoje o fluxo principal do agente passa por estes pontos:

- `AgentService` coordena a execucao do agente, cria/continua conversa, aplica fact-check, registra falhas e trata respostas sem envio.
- `BaseCustomerServiceAgent` monta contexto, prompt, memoria, ferramentas e configuracao de modelo/provider.
- `FactCheckService` valida valores monetarios e prazos contra `credito_json` antes de liberar uma resposta.
- `DataFidelityValidator` mede fidelidade de resposta em cenarios de laboratorio/stress test.
- `ToolCallGuardMiddleware`, `TokenBudgetMiddleware` e `AuditLogMiddleware` adicionam controle de uso, logs e protecoes.
- Ferramentas como `ConsultarCreditoInssTool`, `EscalarParaHumanoTool`, `AtualizarStatusLeadTool` e `RegistrarLeadSemCreditoTool` ja possuem parte importante das regras de negocio.
- `/laboratory` ja mostra falhas, recuperacao, follow-up, disparos em massa, uso de IA, health check, datasets e stress tests.

Os principais riscos atuais sao:

- Conversas e mensagens podem ficar fragmentadas entre inbound, outbound, campanhas, follow-up, URA e humano.
- Debug de uma resposta especifica ainda depende de juntar logs, jobs, mensagens, uso de IA e ferramentas manualmente.
- O fact-check e forte, mas precisa ficar mais conservador em casos de timeout, ausencia de dados ou resposta com valor financeiro.
- O controle de ferramentas precisa bloquear loops mais cedo, nao apenas registrar excesso.
- O futuro sistema de creditos precisa controlar custo antes da chamada ao modelo, nao somente medir depois.
- O laboratorio precisa sair de "painel de informacao" para "central de operacao": qualidade, custo, risco, funil e diagnostico por conversa.

## Principios de Arquitetura

- Um evento importante deve ter um `interaction_id` rastreavel de ponta a ponta.
- Toda mensagem deve passar por uma timeline unica, independente de origem: WhatsApp, campanha, follow-up, URA, humano ou sistema.
- Toda saida real para cliente deve passar por outbox, idempotencia e registro de status.
- Resposta com dinheiro, prazo, proposta ou elegibilidade deve falhar fechada quando nao houver validacao confiavel.
- Toda chamada cara ou arriscada deve ter preflight: creditos, limite, kill switch, estado do tenant e estado do canal.
- O painel operacional deve responder rapidamente: "o que quebrou?", "quanto custa?", "qual campanha vende?", "qual agente esta arriscado?", "qual conversa preciso abrir?".
- Prompts, ferramentas e templates precisam ser versionados para permitir rollback e comparacao.

## Fase 0: Linha de Base e Criterios de Sucesso

Objetivo: definir o que significa "sistema confiavel" antes de mexer em muita coisa.

### 0.1. Mapear fluxos reais

Fluxos que precisam estar documentados:

- Lead entra pelo WhatsApp e recebe resposta IA.
- Lead vem de campanha de template e responde.
- Lead vem de follow-up automatico.
- Lead vem da URA reversa e cai no WhatsApp.
- Agente consulta CPF e gera proposta.
- Agente escala para humano.
- Humano assume conversa e o agente para.
- Tenant fica sem credito.
- Meta/API externa falha.
- Fact-check reprova resposta.
- Cliente pede opt-out.

Entregavel:

- Documento curto com os fluxos acima, arquivos envolvidos e eventos gerados.
- Tabela de status possiveis do lead, conversa, mensagem, campanha e ticket.

Criterio de aceite:

- Para cada fluxo deve ser possivel apontar onde ele comeca, qual job/processador executa, onde salva mensagem, onde salva custo, onde falha e onde aparece no laboratorio.

### 0.2. Definir metricas principais

Metricas minimas:

- Taxa de resposta de campanha.
- Taxa de qualificacao.
- Taxa de escalonamento para humano.
- Taxa de respostas bloqueadas por fact-check.
- Taxa de erro por ferramenta.
- Tokens e custo por tenant, agente, campanha e modelo.
- Custo por lead qualificado.
- Tempo medio de primeira resposta.
- Tempo medio de ferramenta externa.
- Conversas com loop de ferramenta.

Entregavel:

- Lista oficial de metricas com definicao, formula, origem dos dados e frequencia de atualizacao.

Criterio de aceite:

- Duas pessoas diferentes devem conseguir calcular a mesma metrica usando a mesma definicao.

## Fase 1: Rastreabilidade de Ponta a Ponta

Objetivo: qualquer bug em conversa deve ser investigavel em minutos.

### 1.1. Criar `interaction_id`

Adicionar um identificador unico por turno de conversa. Ele deve nascer no primeiro ponto de entrada do evento:

- Webhook inbound do WhatsApp.
- Job de campanha.
- Job de follow-up.
- Job de URA reversa.
- Acao manual do operador.
- Reprocessamento pelo laboratorio.

Campos recomendados:

- `interaction_id`
- `tenant_id`
- `lead_id`
- `agent_id`
- `conversation_id`
- `campaign_id`
- `channel`
- `source`
- `provider_message_id`
- `job_id`
- `request_id`

Propagacao obrigatoria:

- Controller/webhook.
- Job.
- `AgentService`.
- Middleware de IA.
- Ferramentas.
- Envio WhatsApp.
- Broadcast para frontend.
- Logs.
- Sentry/Langfuse.
- Tabelas de auditoria.

Entregavel:

- Helper ou service para criar e carregar contexto operacional.
- Logs estruturados sempre contendo `interaction_id`.

Criterio de aceite:

- Ao pegar uma mensagem no frontend, deve ser possivel encontrar todo o rastro: inbound, prompt, modelo, ferramentas, resposta, fact-check, envio ao WhatsApp e atualizacao de UI.

### 1.2. Criar tabela de eventos do agente

Sugestao de tabela: `agent_interaction_events`.

Campos:

- `id`
- `interaction_id`
- `tenant_id`
- `lead_id`
- `agent_id`
- `event_type`
- `event_source`
- `severity`
- `payload_json`
- `created_at`

Eventos importantes:

- `inbound_received`
- `agent_started`
- `prompt_built`
- `model_called`
- `tool_called`
- `tool_succeeded`
- `tool_failed`
- `fact_check_passed`
- `fact_check_failed`
- `fact_check_skipped`
- `outbound_queued`
- `outbound_sent`
- `outbound_failed`
- `human_escalated`
- `kill_switch_blocked`
- `credit_preflight_failed`

Entregavel:

- Model, migration e service para gravar eventos de forma leve.

Criterio de aceite:

- O laboratorio deve conseguir montar uma linha do tempo tecnica de uma conversa usando somente `interaction_id`.

## Fase 2: Timeline Unica de Conversas e Outbox

Objetivo: fazer as conversas aparecerem no frontend de forma completa e evitar perda/duplicidade de envio.

### 2.1. Criar contrato unico de mensagem

Toda mensagem exibida no frontend deve obedecer ao mesmo contrato:

- `id`
- `tenant_id`
- `lead_id`
- `conversation_id`
- `direction`: `inbound`, `outbound`, `internal`
- `sender_type`: `lead`, `agent`, `human`, `system`
- `channel`: `whatsapp`, `voice`, `internal`
- `body`
- `media`
- `status`: `received`, `queued`, `sending`, `sent`, `delivered`, `read`, `failed`
- `source`: `campaign`, `followup`, `manual`, `agent`, `ura`, `webhook`
- `interaction_id`
- `provider_message_id`
- `created_at`

Entregavel:

- `ConversationTimelineService` para gravar mensagens.
- Refatorar fluxos existentes para usar esse service antes de atualizar UI.

Criterio de aceite:

- Uma conversa no frontend deve mostrar: mensagem recebida, resposta da IA, template de campanha, follow-up, intervencao humana e erro de envio, todos no mesmo historico.

### 2.2. Implementar outbox de WhatsApp

Criar uma tabela de outbox para saidas reais:

- `id`
- `tenant_id`
- `lead_id`
- `channel`
- `provider`
- `payload_json`
- `status`
- `idempotency_key`
- `provider_message_id`
- `attempts`
- `last_error`
- `scheduled_at`
- `sent_at`

Regras:

- Nenhum envio direto sem registrar outbox.
- Reenvio deve usar `idempotency_key`.
- Falha deve ficar visivel no laboratorio e na conversa.
- Status callback da Meta deve atualizar a mensagem original.

Entregavel:

- Model, migration, job de processamento e adaptador WhatsApp.

Criterio de aceite:

- Se um job cair no meio do envio, o sistema nao deve duplicar mensagem nem perder o estado operacional.

## Fase 3: Endurecimento do Agente e das Ferramentas

Objetivo: reduzir alucinacao, loop, proposta errada e bug silencioso.

### 3.1. Fact-check falhando fechado

Regra atual boa: validar valores e prazos contra `credito_json`.

Melhorias:

- Se a resposta mencionar dinheiro, parcela, prazo, taxa, contrato, elegibilidade ou proposta e o fact-check nao conseguir validar, bloquear resposta automatica.
- Se o fact-check estourar timeout, nao enviar resposta financeira sem validacao.
- Se `credito_json` estiver ausente, permitir apenas resposta neutra ou pedir CPF/consulta.
- Registrar motivo do bloqueio em `agent_interaction_events`.
- Criar fila de revisao humana para respostas bloqueadas.

Entregavel:

- Ajuste no `FactCheckService` ou no ponto de chamada do `AgentService`.
- Testes com resposta financeira sem dados, resposta financeira com timeout e resposta neutra sem dados.

Criterio de aceite:

- O agente nunca envia valor financeiro nao validado quando o contexto nao permite conferencias.

### 3.2. Usar `DataFidelityValidator` fora do laboratorio

Aplicacao recomendada:

- Rodar em 100% das respostas de alto risco no inicio.
- Depois reduzir para amostragem quando estiver estavel.
- Alto risco: dinheiro, prazo, proposta, elegibilidade, negativa de credito, orientacao operacional sensivel.

Campos para gravar:

- `fidelity_score`
- `hallucination_count`
- `missing_context_count`
- `risk_level`
- `validator_version`

Entregavel:

- Integracao opcional no `AgentService`.
- Painel no laboratorio com score por agente/modelo/prompt.

Criterio de aceite:

- Uma queda de qualidade por mudanca de prompt ou modelo deve aparecer no laboratorio antes de virar problema comercial.

### 3.3. Bloquear loop de ferramenta mais cedo

Melhorias no `ToolCallGuardMiddleware`:

- Limite por ferramenta deve bloquear, nao apenas logar, quando exceder o teto definido.
- Separar limites por tipo de ferramenta:
  - Consulta externa: limite baixo.
  - Atualizacao de status: limite medio.
  - Ferramentas internas sem custo: limite controlado.
- Gerar evento `tool_loop_blocked`.
- Resposta final deve ser segura: pedir revisao humana ou avisar instabilidade, sem inventar resultado.

Entregavel:

- Configuracao em `config/credflow.php`.
- Testes de loop por ferramenta e limite total.

Criterio de aceite:

- Um erro de prompt nao deve conseguir chamar consulta externa varias vezes na mesma conversa.

### 3.4. Contratos explicitos para ferramentas

Cada ferramenta deve declarar:

- Se consome credito.
- Se chama API externa.
- Se altera estado do lead.
- Se pode enviar mensagem.
- Se precisa de CPF.
- Se pode rodar em campanha.
- Se pode rodar em follow-up.
- Se exige tenant ativo.

Entregavel:

- Interface ou metadata padrao para tools.
- Validacao antes da execucao.

Criterio de aceite:

- Uma ferramenta nova nao entra em producao sem declarar custo, risco e permissoes.

### 3.5. Restringir `GenericWebhookTool`

Risco: webhook generico e poderoso demais se for mal configurado.

Melhorias:

- Allowlist por tenant/dominio.
- Timeout curto.
- Schema de entrada e saida obrigatorio.
- Mascara de dados sensiveis nos logs.
- Kill switch especifico.
- Ambiente de teste antes de producao.

Entregavel:

- Validacoes de configuracao e painel de status.

Criterio de aceite:

- Um webhook generico ruim nao pode derrubar o agente inteiro nem vazar dado sensivel em log.

## Fase 4: Creditos por Tenant e Kill Switches

Objetivo: controlar custo, impedir abuso e parar partes do sistema sem derrubar tudo.

### 4.1. Modelo de creditos

Tabelas sugeridas:

- `tenant_credit_accounts`
- `tenant_credit_transactions`
- `tenant_usage_limits`
- `tenant_runtime_controls`

`tenant_credit_accounts`:

- `tenant_id`
- `balance`
- `reserved_balance`
- `monthly_quota`
- `grace_balance`
- `status`: `active`, `soft_limited`, `hard_limited`, `suspended`

`tenant_credit_transactions`:

- `tenant_id`
- `interaction_id`
- `type`: `reserve`, `commit`, `refund`, `adjustment`, `grant`
- `feature`
- `model`
- `tokens_input`
- `tokens_output`
- `credits`
- `cost_cents`
- `metadata_json`

`tenant_usage_limits`:

- `tenant_id`
- `feature`
- `period`: `minute`, `hour`, `day`, `month`
- `max_tokens`
- `max_credits`
- `max_messages`
- `max_tool_calls`

### 4.2. Fluxo reserve/commit/refund

Antes de chamar o modelo:

- Estimar custo maximo com base em modelo, prompt, limite de saida e ferramentas permitidas.
- Reservar creditos.
- Se nao houver saldo, bloquear nova chamada IA.

Depois da chamada:

- Capturar tokens reais via middleware/log de uso.
- Commit do valor real.
- Refund da diferenca reservada.

Em caso de erro:

- Refund se o modelo nao respondeu.
- Commit parcial se houve consumo real.

Criterio de aceite:

- O tenant nunca estoura custo sem o sistema perceber antes da chamada.

### 4.3. Kill switches

Niveis:

- Global.
- Tenant.
- Agente.
- Canal.
- Campanha.
- Ferramenta.
- Provider.
- Modelo.

Chaves recomendadas:

- `ai_responses_enabled`
- `campaign_dispatch_enabled`
- `followup_enabled`
- `whatsapp_send_enabled`
- `consulta_inss_enabled`
- `consulta_siape_enabled`
- `generic_webhook_enabled`
- `human_escalation_enabled`
- `fact_check_enforced`
- `credit_enforcement_enabled`

Estados:

- `enabled`
- `soft_block`: grava entrada, nao executa acao arriscada, manda para humano/fila.
- `hard_block`: bloqueia imediatamente.
- `maintenance`: resposta padrao de indisponibilidade.

Regras:

- Kill switch nunca deve apagar mensagem recebida.
- Inbound sempre deve ser registrado.
- Se IA estiver bloqueada, abrir fila humana ou resposta operacional segura.
- Se WhatsApp send estiver bloqueado, manter outbox pendente.
- Se consulta externa estiver bloqueada, agente nao pode inventar resultado.

### 4.4. Kill switches automaticos

Acionamentos automaticos:

- Taxa de erro da Meta acima de limite.
- Consulta externa com circuito aberto.
- Fact-check falhando acima de limite.
- Custo por conversa acima de limite.
- Tool loop acima de limite.
- Latencia acima de limite.
- Tenant sem credito.
- Campanha com taxa alta de falha/opt-out.

Entregavel:

- `RuntimeControlService` consultado antes de acoes criticas.
- Painel no laboratorio para visualizar e alterar switches.
- Log de quem alterou, quando e motivo.

Criterio de aceite:

- Deve ser possivel desligar apenas campanhas de um tenant sem desligar atendimento receptivo.

## Fase 5: Laboratory 2.0

Objetivo: transformar `/laboratory` em centro de operacao, qualidade e custo.

Hoje o laboratorio ja cobre:

- Falhas recentes e padroes de erro.
- Recuperacao de interacoes.
- Follow-up engine.
- Disparos em massa.
- Uso de IA por dia/modelo.
- Health check.
- Stress tests e datasets.
- Links para Langfuse e Horizon.

A evolucao recomendada e dividir em paineis por decisao operacional.

### 5.1. Painel "Control Tower"

Pergunta que responde: "posso operar agora?"

Cards:

- Status geral do sistema.
- Filas criticas e jobs atrasados.
- Falhas abertas.
- Kill switches ativos.
- Tenants sem credito ou em limite.
- Meta/API WhatsApp status interno.
- Circuit breakers abertos.
- Erros por minuto.

Acoes:

- Pausar campanhas.
- Pausar IA de um tenant.
- Forcar modo humano.
- Ver incidentes recentes.

### 5.2. Painel "Qualidade da IA"

Pergunta que responde: "a IA esta respondendo bem?"

Metricas:

- Fact-check aprovado/reprovado/ignorado.
- Respostas bloqueadas por alto risco.
- `fidelity_score` medio.
- Alucinacoes detectadas por categoria.
- Respostas sem contexto suficiente.
- No-reply sentinel.
- Tool loops bloqueados.
- Escalonamentos para humano por motivo.
- Qualidade por agente, prompt, modelo e tenant.

Drilldown:

- Abrir `interaction_id`.
- Ver prompt versionado.
- Ver resposta original.
- Ver correcao do fact-check.
- Ver ferramentas chamadas.
- Ver motivo de bloqueio.

### 5.3. Painel "Custo e Creditos"

Pergunta que responde: "quanto esta custando e quem vai ficar sem saldo?"

Metricas:

- Tokens input/output por tenant.
- Creditos consumidos por feature.
- Custo por campanha.
- Custo por lead respondido.
- Custo por lead qualificado.
- Burn rate por hora/dia.
- Projecao de fim de credito.
- Modelos mais caros.
- Prompts com maior consumo.

Alertas:

- Tenant com menos de X dias de saldo.
- Campanha com custo alto e baixa resposta.
- Agente usando modelo caro sem ganho de qualidade.
- Aumento repentino de tokens por conversa.

### 5.4. Painel "Prospeccao e Funil"

Pergunta que responde: "a maquina de vendas esta funcionando?"

Funil:

- Leads importados.
- Templates enviados.
- Entregues.
- Lidos.
- Respondidos.
- Qualificados.
- Consultados.
- Com proposta.
- Escalados.
- Convertidos.

Quebras:

- Por campanha.
- Por template.
- Por lista.
- Por convenio/produto.
- Por horario.
- Por operador.
- Por agente.

Metricas importantes:

- Resposta por template.
- Opt-out por template.
- Tempo ate primeira resposta.
- Tempo ate consulta.
- Tempo ate proposta.
- Taxa de humano necessario.

### 5.5. Painel "Ferramentas e APIs"

Pergunta que responde: "qual dependencia externa esta afetando venda?"

Metricas:

- Latencia por ferramenta.
- Erro por ferramenta.
- Circuit breaker status.
- Consultas por minuto.
- Cache hit de consulta.
- Custo por ferramenta.
- Falhas da Meta/Twilio/APIs de consulta.

Drilldown:

- Ultimos erros.
- Payload sanitizado.
- Tenant afetado.
- Leads afetados.
- Reprocessar quando seguro.

### 5.6. Painel "Debugger de Conversa"

Pergunta que responde: "por que essa conversa fez isso?"

Busca por:

- Telefone.
- CPF.
- Lead.
- `interaction_id`.
- Provider message id.
- Campanha.

Timeline exibida:

- Mensagem inbound.
- Prompt usado.
- Modelo e parametros.
- Tokens e custo.
- Ferramentas chamadas.
- Resultado das ferramentas.
- Fact-check.
- Resposta final.
- Outbox.
- Status Meta.
- Broadcast frontend.
- Escalonamento humano.

Criterio de aceite:

- Um bug report com telefone e horario deve ser investigavel sem abrir terminal.

### 5.7. Estrutura de dados para paineis rapidos

Evitar dashboards pesados consultando tabelas transacionais grandes.

Tabelas agregadas sugeridas:

- `agent_quality_hourly`
- `agent_quality_daily`
- `tenant_ai_usage_hourly`
- `tenant_ai_usage_daily`
- `campaign_funnel_daily`
- `tool_health_hourly`
- `conversation_ops_daily`

Jobs:

- Agregacao a cada 5 minutos para operacional.
- Agregacao diaria para relatorios.
- Backfill manual para recalcular periodos.

Criterio de aceite:

- `/laboratory` deve carregar rapido mesmo com muitas conversas.

## Fase 6: Testes e Regressao

Objetivo: permitir mudar prompt, ferramenta, Meta e fluxo de prospeccao sem medo.

### 6.1. Testes minimos por camada

Unitarios:

- Fact-check.
- Data fidelity.
- Normalizacao de telefone/CPF.
- Calculo de creditos.
- Kill switch resolver.
- Status machine.

Feature:

- WhatsApp inbound cria timeline.
- Campanha cria outbox.
- Follow-up cria outbox.
- Resposta IA passa por fact-check.
- Resposta financeira sem dados e bloqueada.
- Sem credito bloqueia IA, mas salva inbound.
- Kill switch de campanha nao afeta receptivo.
- Escalonamento humano para o agente.

Replay:

- Salvar conversas reais anonimizadas.
- Reexecutar contra novo prompt/modelo.
- Comparar qualidade, tokens e ferramentas.

Stress:

- Usar datasets do laboratorio.
- Rodar contra cenarios de produto: INSS, SIAPE, FGTS, CLT.
- Medir custo, alucinacao, escalonamento e loops.

### 6.2. Golden tests de agente

Criar casos esperados:

- Lead sem CPF.
- CPF invalido.
- CPF valido sem credito.
- CPF valido com credito.
- Cliente pergunta valor.
- Cliente pede taxa inexistente.
- Cliente quer humano.
- Cliente manda audio/imagem.
- Cliente responde campanha.
- Cliente pede parar.

Criterio de aceite:

- Mudanca de prompt ou ferramenta nao entra sem rodar os golden tests principais.

## Fase 7: Operacao, Deploy e Manutencao

Objetivo: o sistema ser facil de manter quando tiver cliente real usando.

### 7.1. Separar filas por criticidade

Filas sugeridas:

- `webhooks`: entrada de eventos.
- `agent`: execucao IA.
- `outbox`: envio WhatsApp.
- `campaigns`: disparos em massa.
- `followup`: follow-ups.
- `analytics`: agregacoes e metricas.
- `low_priority`: tarefas nao urgentes.

Regras:

- Campanha grande nao pode travar atendimento receptivo.
- Analytics nao pode travar envio.
- Follow-up deve respeitar janela e limites.

### 7.2. Runbooks

Criar instrucoes curtas para:

- Meta fora do ar.
- Tenant sem credito reclamando de nao responder.
- Fact-check bloqueando demais.
- Campanha com erro alto.
- Fila atrasada.
- API de consulta fora.
- Mensagens nao aparecem no frontend.
- Custo subiu de repente.

Cada runbook deve ter:

- Sintoma.
- Onde ver no laboratorio.
- Causa provavel.
- Acao segura.
- Como reprocessar.
- Quando escalar.

### 7.3. Versionamento de prompt, ferramenta e template

Campos recomendados:

- `prompt_version`
- `tool_version`
- `template_version`
- `agent_config_version`

Regras:

- Toda resposta deve registrar versoes usadas.
- Rollback deve ser possivel sem deploy quando a mudanca for de prompt/config.
- Stress test deve comparar versoes.

## Ordem Recomendada de Execucao

### Release 1: Observabilidade e conversa aparecendo no frontend

Prioridade maxima porque destrava debug e demonstracao para Meta/cliente.

Entregas:

- `interaction_id`.
- `agent_interaction_events`.
- `ConversationTimelineService`.
- Contrato unico de mensagem.
- Outbox basica.
- Frontend lendo timeline completa.
- Debug simples por conversa no laboratorio.

Criterio de aceite:

- Uma resposta de campanha recebida pelo WhatsApp aparece no frontend com historico completo e pode ser rastreada ate o envio.

### Release 2: Protecoes do agente

Entregas:

- Fact-check falhando fechado.
- Bloqueio real de loop de ferramenta.
- Contratos de ferramentas.
- Eventos de qualidade.
- Testes de regressao principais.

Criterio de aceite:

- O agente nao envia proposta financeira sem validacao e nao consegue repetir consulta externa em loop.

### Release 3: Creditos e kill switches

Entregas:

- Conta de creditos por tenant.
- Reserve/commit/refund.
- Limites por feature.
- Runtime controls.
- Kill switches manuais.
- Primeiros kill switches automaticos.

Criterio de aceite:

- Tenant sem credito nao consome IA, mas mensagens recebidas continuam salvas e visiveis.

### Release 4: Laboratory 2.0

Entregas:

- Control Tower.
- Qualidade da IA.
- Custo e creditos.
- Prospeccao e funil.
- Ferramentas e APIs.
- Debugger de conversa.
- Agregacoes horarias/diarias.

Criterio de aceite:

- Operador consegue identificar em menos de 5 minutos se o problema e IA, Meta, credito, campanha, fila, ferramenta externa ou frontend.

## Backlog Priorizado

### P0

- Criar `interaction_id` e propagar nos fluxos principais.
- Criar timeline unica de mensagens.
- Garantir que campanha/follow-up/WhatsApp inbound/outbound aparecem no frontend.
- Criar outbox basica para WhatsApp.
- Fact-check falhar fechado em resposta financeira nao validada.
- Bloquear loop por ferramenta.
- Criar painel minimo de debugger por conversa.

### P1

- Creditos por tenant com reserve/commit/refund.
- Kill switches manuais por tenant, feature e canal.
- Painel de custo e creditos.
- Painel de qualidade da IA.
- Contratos explicitos para tools.
- Replay tests com conversas reais anonimizadas.
- Separacao de filas por criticidade.

### P2

- Kill switches automaticos.
- Agregacoes horarias e diarias completas.
- Comparacao de prompt/modelo no laboratorio.
- Painel de funil comercial completo.
- Runbooks operacionais.
- Otimizacao de custo por modelo/prompt.

## Riscos e Mitigacoes

| Risco | Impacto | Mitigacao |
| --- | --- | --- |
| Agente inventar valor | Alto | Fact-check fail-closed, DataFidelityValidator em alto risco, revisao humana |
| Campanha duplicar mensagem | Alto | Outbox com idempotencia |
| Tenant gastar demais | Alto | Preflight de credito, reserve/commit/refund, limites por feature |
| Bug dificil de rastrear | Alto | `interaction_id`, eventos estruturados, debugger de conversa |
| API externa ficar fora | Medio/alto | Circuit breaker, kill switch por provider, resposta segura |
| Loop de ferramenta | Medio/alto | Limite por ferramenta com bloqueio real |
| Dashboard ficar lento | Medio | Tabelas agregadas e jobs de consolidacao |
| Campanha afetar atendimento receptivo | Alto | Filas separadas e limites de concorrencia |
| Webhook generico mal configurado | Alto | Allowlist, schema, timeout, kill switch e logs sanitizados |

## Definicao de Pronto

Uma fase so deve ser considerada pronta quando:

- Tem migration/model/service quando necessario.
- Tem testes automatizados para os cenarios principais.
- Gera eventos no laboratorio.
- Tem criterio de aceite validado manualmente.
- Tem fallback seguro para falha.
- Nao quebra o fluxo receptivo.
- Nao perde mensagem recebida.
- Nao envia resposta financeira sem validacao.

## Opiniao Tecnica

O Aria ja tem uma fundacao acima do normal para um primeiro projeto: existe preocupacao real com guardrails, auditoria, testes, laboratorio e fluxos de negocio. O risco agora nao e "a IA fazer qualquer coisa"; o risco maior e operacional: custo sem limite, mensagem fora da timeline, debug lento, campanha misturada com atendimento receptivo e paineis que mostram informacao mas nao ajudam a decidir rapido.

O caminho mais seguro e fortalecer a operacao antes de expandir muito feature. Para o modo prospeccao virar maquina de vendas, a prioridade deve ser:

1. Conversa aparecer inteira no frontend.
2. Cada mensagem ter rastreabilidade.
3. Toda saida passar por outbox.
4. Agente nunca responder proposta sem validacao.
5. Credito e kill switch entrarem antes de escala comercial.
6. Laboratory virar painel de decisao, nao apenas painel de logs.

Com isso, o sistema fica mais facil de manter porque cada problema passa a ter um lugar claro para olhar, uma trilha de eventos e uma acao operacional segura.
