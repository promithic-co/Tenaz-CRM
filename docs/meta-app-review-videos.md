# Roteiro para videos da revisao Meta WhatsApp

Objetivo: gravar dois videos curtos e objetivos para a Analise do App da Meta.

- `whatsapp_business_management`: mostrar criacao e salvamento de um modelo de mensagem no WhatsApp Manager.
- `whatsapp_business_messaging`: mostrar uma mensagem enviada pela Aria usando WhatsApp Cloud API e recebida no WhatsApp do numero de teste.

Nao mostre tokens, app secret, `.env`, headers `Authorization`, chaves de API, dados reais de clientes, CPF ou conversas reais.

## Preparacao

Antes de gravar:

1. Use um numero de WhatsApp de teste que voce controla.
2. Garanta que a instancia da Aria esta como Meta Cloud API em `/whatsapp`.
3. Garanta que existe um lead de teste em `/conversas` com esse numero.
4. Se for enviar mensagem livre pela tela de conversa, primeiro mande uma mensagem do WhatsApp de teste para o numero oficial da Aria para abrir a janela de 24h.
5. Abra o WhatsApp Web ou deixe o celular visivel na gravacao para mostrar o recebimento.
6. Feche abas com dados sensiveis e use zoom de 100% ou 110%.

Mensagem de teste sugerida:

```text
Teste de envio da Aria via WhatsApp Cloud API para revisao da Meta.
```

## Video 1 - Criacao de modelo de mensagem

Permissao: `whatsapp_business_management`.

Duracao ideal: 45 a 90 segundos.

Gravar no Meta Business/WhatsApp Manager, nao na tela `/templates` da Aria. A tela `/templates` da Aria serve para registrar ou sincronizar templates ja existentes, mas a Meta pede a criacao/salvamento no Gerenciador do WhatsApp.

Roteiro:

1. Abra o Meta Business Suite ou WhatsApp Manager.
2. Entre na conta WhatsApp Business usada pela Aria.
3. Abra a area de modelos de mensagem.
4. Clique para criar um novo modelo.
5. Escolha uma categoria simples, preferencialmente `Utility`, se fizer sentido para o texto.
6. Preencha:
   - Nome: `aria_teste_revisao_meta`
   - Idioma: `Portuguese (BR)` ou `pt_BR`
   - Corpo: `Ola, {{1}}. Esta e uma mensagem de teste da Aria para validar o envio pela WhatsApp Cloud API.`
   - Exemplo da variavel `{{1}}`: `Cliente Teste`
7. Salve ou envie para analise.
8. Termine mostrando a tela onde o modelo aparece salvo/criado.

Narracao opcional:

```text
Estou criando um modelo de mensagem na conta WhatsApp Business conectada a Aria. O modelo sera usado para mensagens iniciadas pela empresa via WhatsApp Cloud API. Agora salvo o modelo e confirmo que ele aparece no Gerenciador do WhatsApp.
```

## Video 2 - Envio de mensagem pela API Cloud

Permissao: `whatsapp_business_messaging`.

Duracao ideal: 60 a 120 segundos.

Roteiro recomendado usando a Aria online:

1. Deixe a tela dividida:
   - esquerda: Aria no navegador;
   - direita: WhatsApp Web ou o celular recebendo a mensagem.
2. Na Aria, abra `/whatsapp`.
3. Mostre rapidamente a instancia conectada como `Meta Cloud API` ou `Conexao via Meta Cloud API`.
4. Abra `/conversas`.
5. Entre no lead de teste vinculado ao seu numero.
6. Se a conversa estiver vazia ou fora da janela, envie primeiro uma mensagem do WhatsApp de teste para o numero oficial da Aria e aguarde aparecer na conversa.
7. Na Aria, digite a mensagem de teste no campo `Digite uma mensagem...`.
8. Clique no botao de envio.
9. Mostre a mensagem aparecendo na conversa da Aria.
10. Mostre a mesma mensagem chegando no WhatsApp Web ou celular.
11. Se a resposta do WhatsApp de teste voltar para a Aria, mostre a mensagem inbound aparecendo em `/conversas`.

Narracao opcional:

```text
A Aria esta usando uma instancia conectada pela WhatsApp Cloud API oficial. Agora envio uma mensagem de teste pelo painel de conversas da Aria para meu numero de WhatsApp. A mensagem sai pelo backend da Aria, passa pela Cloud API e chega no WhatsApp do destinatario.
```

## Alternativa com a ferramenta Try it out da Meta

Use esta alternativa se o avaliador exigir explicitamente a ferramenta da Meta.

1. Abra a pagina da permissao e clique em `Ir para "Testar"`.
2. Complete a etapa 1 da ferramenta.
3. Na etapa 2, copie o comando cURL, mas nao mostre o token completo na gravacao.
4. Envie a mensagem para o numero de teste.
5. Mostre o recebimento no WhatsApp.
6. Se quiser relacionar com a Aria, em seguida mostre que o webhook da Aria recebe a resposta na tela `/conversas`.

## Checklist final antes de enviar

- O video 1 mostra o WhatsApp Manager criando/salvando um template.
- O video 2 mostra a Aria enviando uma mensagem e o WhatsApp recebendo.
- Nenhum token, secret, CPF, conversa real ou dado de cliente aparece.
- O numero receptor e de teste.
- A mensagem enviada tem texto claro mencionando teste/revisao.
- Os videos estao em MP4, com resolucao legivel e sem cortes que escondam o clique de envio/salvamento.
