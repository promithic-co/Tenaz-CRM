# Plano de melhoria da tela de templates Meta HSM

## Diagnostico

A tela atual deve deixar claro se o usuario esta sincronizando templates ja existentes na Meta ou criando um novo template pela API da Meta. O fluxo de criacao real nao deve pedir campos que sao automaticos da instancia ou gerados pela propria Meta.

O problema principal observado na tela atual e que `Template ID`, `WABA ID` e `Status` aparecem como inputs editaveis. Isso cria uma UX confusa e tecnicamente incorreta:

- `WABA ID` pertence a instancia WhatsApp Meta Cloud selecionada.
- `Template ID` e gerado pela Meta quando o template e criado, ou vem da sincronizacao de templates existentes.
- `Status` e resultado do lifecycle da Meta, nao uma escolha operacional do usuario.

Na Graph API, a criacao usa o endpoint `POST /{whatsapp-business-account-id}/message_templates`. O `whatsapp-business-account-id` e o WABA ID da instancia. O payload de criacao envia dados como `name`, `category`, `language` e `components`. Depois disso, a Meta retorna dados do template, incluindo o `id` quando disponivel, e o status passa a ser atualizado por retorno da API, sincronizacao ou webhook.

Na API e nos fluxos de parceiros, o template e submetido para aprovacao e passa por estados como `PENDING`, `APPROVED`, `REJECTED`, `PAUSED`, `DISABLED`, `FLAGGED`, `IN_APPEAL`, `PENDING_DELETION`, `DELETED` e `LIMIT_EXCEEDED`. Esses estados sao resultado de revisao, qualidade e webhooks/sincronizacao, nao uma escolha do usuario.

O app ja tem uma base boa para o caminho correto:

- `SyncMetaTemplatesJob` busca templates em `/{waba_id}/message_templates` e salva `status`, `category`, `language`, `components_json`, `quality_score` e `rejected_reason`.
- `MetaWebhookController` trata `message_template_status_update`.
- `WhatsappTemplate` ja possui campos para `components_json`, `quality_score` e `rejected_reason`.
- `MetaTemplateService` ja representa o lugar correto para montar payload e chamar a Graph API.

O foco da melhoria deve ser simplificar a tela e separar entradas do usuario de dados automaticos.

## Referencias pesquisadas

- Meta WhatsApp Business Platform: Message Templates e endpoint `/{whatsapp-business-account-id}/message_templates`.
- Verificacao atualizada: na criacao de template, o WABA ID identifica a conta no caminho da API; `name`, `category`, `language` e `components` compoem o payload; `Template ID` e `Status` sao retornados, sincronizados ou atualizados pela Meta.
- Twilio Content Templates: separa criacao, envio para aprovacao, consulta de status e envio.
- 360dialog Templates: depois de criado/editado, o template e submetido automaticamente para aprovacao da Meta; so pode ser usado apos aprovacao.
- respond.io Templates: lista templates com status, qualidade, motivo de rejeicao e ultima sincronizacao; edicao reenvia para aprovacao.
- Infobip Template Compliance: status ativo tem qualidade; quedas de qualidade podem levar a pausa/desativacao.

## Conclusoes sobre a tela atual

### Campo `Status`

Deve sair dos formularios normais de criar/editar.

Novo comportamento:

- Na criacao real pela Meta: salvar localmente como `PENDING` ou usar o status retornado pela API.
- Na sincronizacao: status sempre vem da Meta.
- No webhook: status e atualizado automaticamente.
- Na listagem/detalhe: status aparece como badge read-only.
- Override manual, se necessario para suporte, deve ficar fora do fluxo comum e protegido por permissao/admin.

### Campo `Template ID`

Deve sair do formulario de criacao.

Novo comportamento:

- Na criacao pela Meta: `Template ID` e salvo a partir do `id` retornado pela API.
- Na sincronizacao: `Template ID` vem do `GET /{waba_id}/message_templates`.
- Na listagem/detalhe: aparece como texto read-only, quando existir.
- Nunca deve ser digitado pelo usuario no fluxo comum, porque isso cria risco de associar o registro local ao template errado.

### Campo `WABA ID`

Deve sair do formulario de criacao como input.

Novo comportamento:

- O usuario seleciona a `Instancia WhatsApp Meta Cloud`.
- O backend usa o `meta_waba_id` configurado nessa instancia para chamar `POST /{waba_id}/message_templates`.
- A UI pode mostrar uma linha read-only apos selecionar a instancia, por exemplo: `Conta WABA vinculada: 123...`.
- Se a instancia nao tiver `meta_waba_id` ou token configurado, o formulario deve bloquear o envio e orientar a configurar a instancia.

### Campo `Nome de Exibicao`

Deve ser renomeado ou separado em dois conceitos:

- `Nome interno`: livre, para organizacao dentro da Aria.
- `Nome Meta`: obrigatorio para criacao na Meta, com validacao de lowercase, numeros e underscore, sem espacos.

Para reduzir friccao, `Nome interno` pode ser preenchido automaticamente a partir do `Nome Meta`, mas deve continuar editavel.

### Campo `Corpo do Template`

Esta correto como parte central, mas precisa de validacao e preview melhores para HSM real.

Melhorias:

- Validar limite de 1024 caracteres.
- Validar placeholders `{{1}}`, `{{2}}`, etc. sequenciais.
- Bloquear placeholder no inicio/fim ou placeholders adjacentes, porque isso aumenta rejeicao.
- Pedir exemplo para cada variavel antes de submeter.
- Mostrar preview do WhatsApp com as variaveis substituidas.

### Categoria e idioma

Faz sentido manter na criacao, mas com orientacao melhor.

Categorias:

- `UTILITY`: transacao, atendimento solicitado, lembrete, confirmacao, follow-up de solicitacao.
- `MARKETING`: promocao, reengajamento, oferta ou conteudo que nao e estritamente transacional.
- `AUTHENTICATION`: OTP/codigo de verificacao, com estrutura propria.

Depois de aprovado, categoria e idioma nao devem ser editados livremente no app. Edicao deve seguir regra da Meta: alterar conteudo e reenviar para aprovacao, ou criar nova versao.

## Tela proposta

### 1. Listagem

Manter a listagem, mas enriquecer:

- Colunas: nome interno, nome Meta, categoria, idioma, status, qualidade, variaveis, instancia, ultima sincronizacao.
- Exibir `rejected_reason` quando status for `REJECTED`.
- Exibir `quality_score` em templates aprovados.
- Incluir status adicionais suportados pela Meta/parceiros: `PAUSED`, `DISABLED`, `FLAGGED`, `IN_APPEAL`, `PENDING_DELETION`, `DELETED`, `LIMIT_EXCEEDED`, mesmo que nem todos sejam acionaveis no app.
- Acoes: sincronizar, duplicar, ver payload/componentes, abrir no WhatsApp Manager.

### 2. Criacao simplificada

Trocar `Registrar Template` por duas acoes distintas e mais claras:

- `Sincronizar da Meta`: fluxo recomendado quando o template ja foi criado no WhatsApp Manager.
- `Criar na Meta`: builder simplificado que chama a API de criacao de template.

Fluxo do builder:

1. Selecionar `Instancia WhatsApp Meta Cloud`.
2. Mostrar dados automaticos da instancia em leitura:
   - WABA ID vinculado;
   - status de configuracao da instancia, como token/WABA presentes ou ausentes.
3. Preencher os dados que realmente fazem parte do template:
   - Nome Meta;
   - Nome interno;
   - Categoria;
   - Idioma;
   - Body;
   - Exemplos das variaveis detectadas.
4. Revisar preview e checklist.
5. Enviar para aprovacao da Meta.

Campos de entrada do builder na versao simples:

- Instancia WhatsApp Meta Cloud.
- Nome Meta.
- Nome interno.
- Categoria.
- Idioma.
- Body obrigatorio.
- Exemplos de variaveis.
- Preview.
- Checklist de validacao antes de enviar.

Campos que nao entram como input de criacao:

- `Template ID`: gerado pela Meta.
- `WABA ID`: vem da instancia selecionada.
- `Status`: definido pela Meta.
- `Quality score`: so existe depois de aprovado/avaliado pela Meta.
- `Rejected reason`: so existe depois de rejeicao.

Campos para uma fase posterior do builder:

- Header opcional: nenhum, texto, imagem, video, documento ou localizacao, conforme suporte escolhido.
- Footer opcional.
- Botoes opcionais: quick reply, URL, telefone, codigo de copia, Flow quando aplicavel.

Ao submeter:

- Backend monta `components`.
- Backend chama `POST /{waba_id}/message_templates`.
- Backend usa `meta_waba_id` e token da instancia selecionada.
- Registro local salva `meta_template_id` a partir do `id` retornado pela Meta, quando vier.
- Registro local nasce como `PENDING` ou com o status retornado pela API.
- Sync/webhook continua sendo a fonte da verdade.

### 3. Edicao

Substituir modal atual por detalhe read-only + acoes.

Campos read-only:

- Status.
- Template ID.
- WABA ID.
- Categoria.
- Idioma.
- Nome Meta.
- Body/componentes sincronizados.
- Qualidade.
- Motivo de rejeicao.

Acoes:

- `Editar e reenviar`: permitido apenas quando status e compativel com edicao. Deve chamar a API da Meta ou encaminhar para WhatsApp Manager.
- `Duplicar como novo template`: recomendado para iterar sem quebrar campanhas existentes.
- `Sincronizar agora`.

Nao permitir salvar localmente um body diferente do body aprovado na Meta, porque isso quebra a correspondencia entre o que a campanha mostra e o que a Meta realmente enviara.

## Plano tecnico

1. Remover `status` dos formularios `registerForm` e `editForm` em `resources/js/pages/templates/Index.vue`.
2. Remover `meta_template_id` e `meta_waba_id` dos inputs de criacao em `resources/js/pages/templates/Index.vue`.
3. Ao selecionar a instancia, exibir `meta_waba_id` apenas como informacao read-only, se isso ajudar o usuario a confirmar a conta.
4. Bloquear envio quando a instancia Meta Cloud nao tiver `meta_waba_id` ou token configurado.
5. Trocar os selects de status por badge read-only no detalhe/edicao.
6. Ajustar `StoreWhatsappTemplateRequest` e `UpdateWhatsappTemplateRequest` para nao aceitar `status`, `meta_template_id` ou `meta_waba_id` no fluxo comum.
7. No `WhatsappTemplateController`, definir status e ids locais por origem:
   - criacao via Meta: retorno da API ou `PENDING`;
   - `meta_template_id`: `id` retornado pela API ou valor vindo do sync;
   - `meta_waba_id`: valor da instancia selecionada;
   - sync: valores vindos da Meta;
   - edicao comum: preservar status existente.
8. Ampliar `statusBadgeClass` para os status adicionais.
9. Mostrar `quality_score`, `rejected_reason`, `last_synced_at` e `components_json` na linha expandida.
10. Criar ou consolidar endpoint de criacao Meta usando a instancia selecionada e `meta_waba_id`.
11. Criar ou consolidar service dedicado, por exemplo `MetaTemplateService`, para montar payload e chamar Graph API.
12. Validar nome Meta, categoria, idioma, componentes, placeholders e exemplos antes de chamar a Meta.
13. Atualizar testes:
    - usuario nao consegue marcar template como `APPROVED` manualmente;
    - usuario nao consegue informar `Template ID` manualmente na criacao Meta;
    - usuario nao consegue informar `WABA ID` manualmente na criacao Meta;
    - backend usa `meta_waba_id` da instancia selecionada;
    - `meta_template_id` e salvo a partir do retorno da API Meta;
    - criacao Meta salva `PENDING`;
    - sync/webhook atualiza status;
    - template `PAUSED`/`DISABLED` bloqueia campanha;
    - rejeicao mostra `rejected_reason`.

## Prioridade recomendada

1. Curto prazo: simplificar modal de criacao removendo `Template ID`, `WABA ID` e `Status` como inputs.
2. Curto prazo: selecionar instancia primeiro e mostrar dados automaticos da instancia em leitura.
3. Curto prazo: expor qualidade/rejeicao/ultima sync apenas em listagem/detalhe.
4. Curto prazo: melhorar validacoes de placeholders e exemplos de variaveis.
5. Medio prazo: separar visualmente `Sincronizar da Meta` de `Criar na Meta`.
6. Medio prazo: implementar builder de componentes completo.
7. Longo prazo: suportar botoes, media header, Flow templates e edicao/reenvio controlado.

## Decisao de produto

Se o objetivo e apenas usar templates aprovados em campanhas, a tela deve ser uma tela de sincronizacao/gestao e nao de criacao.

Se o objetivo e permitir criar templates pelo app, a tela precisa virar um builder de template Meta e chamar a API de template management. Nesse caso, `Template ID`, `WABA ID` e `Status` continuam automaticos/read-only: a instancia informa o WABA, e a Meta e a autoridade sobre ID, aprovacao, qualidade e rejeicao.
