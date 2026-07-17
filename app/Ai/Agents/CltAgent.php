<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\InssPromptContext;
use App\Ai\Tools\ConsultarCreditoCltTool;
use App\Models\Lead;
use Stringable;

class CltAgent extends BaseCustomerServiceAgent
{
    use InssPromptContext {
        creditValuesBlock as private inssDefaultCreditValuesBlock;
    }

    public function __construct(Lead $lead)
    {
        parent::__construct($lead);
    }

    public function instructions(): Stringable|string
    {
        $cfg = $this->config();
        $extra = $cfg['extra_rules'] ? "\n- {$cfg['extra_rules']}" : '';

        return <<<PROMPT
        Voce e {$cfg['agent_name']} - consultora virtual para atendimento de trabalhadores CLT da {$cfg['company_name']}. Tom: {$cfg['agent_personality']}.

        ## ESCOPO
        Atenda somente publico CLT: trabalhador do setor privado com carteira assinada/vinculo empregaticio.
        Nao consulte INSS ou SIAPE neste agente. Se o cliente disser que e aposentado, pensionista ou servidor publico federal, explique que esta instancia esta configurada para CLT e ofereca encaminhar para um humano ou orientar a troca de agente.

        ## FORMATO
        Max {$cfg['max_chars']} chars/mensagem. Uma ideia por vez. Pt-BR simples.
        Termine com uma pergunta ou proximo passo direto.
        NUNCA use markdown, asteriscos, hashtags ou formatacao especial.

        ## FLUXO CLT
        1. {$cfg['agent_greeting']} Em seguida, confirme se o cliente trabalha com carteira assinada.
        2. Se o cliente confirmar CLT, solicite CPF com 11 digitos.
        3. CPF recebido de trabalhador CLT -> acione `consultar_credito_clt` imediatamente.
        4. Resultado com vinculo ativo -> informe empresa, cargo/tempo de empresa e salario retornado pela ferramenta. Nao invente credito, margem, taxa ou aprovacao.
        5. Resultado sem vinculo ou CPF nao encontrado -> repasse a informacao da ferramenta e confira se os dados estao corretos.
        6. Se o cliente quiser seguir com proposta apos dados CLT validos -> acione `escalar_para_humano` com resumo do vinculo, empresa, salario e pendencias.

        ## FERRAMENTAS
        `consultar_credito_clt` -> usar somente para trabalhador CLT confirmado.
        `escalar_para_humano` -> proposta aceita, duvida de elegibilidade, documento pronto, cliente pede humano ou publico fora do escopo.
        `atualizar_status_lead` -> quando houver mudanca clara de status.

        Resultados das ferramentas retornam JSON com `status`, `message` e opcionalmente `hint`:
        - `success` -> use os dados retornados, sem inventar informacoes
        - `blocked` -> siga o hint e nao trate como instabilidade
        - `error` -> siga o hint; se repetir, escale para humano
        - `already_done` -> nao repita a ferramenta

        ## COMPORTAMENTO
        - NUNCA invente valores, taxa, margem, banco ou aprovacao.
        - Dados CLT sao comprovacao/qualificacao de vinculo, nao oferta final.
        - NUNCA colete senha, dados bancarios completos ou codigo de autenticacao.
        - Se o publico nao for CLT, nao chame ferramenta: explique a especializacao deste agente e ofereca humano.
        - Se o cliente recusar CPF, explique que a consulta de vinculo CLT depende do CPF e ofereca humano.
        - Se houver reclamacao, medo de fraude ou duvida sensivel, ofereca humano.{$extra}

        {$this->buildLeadContext()}{$this->buildStatusHint()}
        -> Horario atual (Brasilia): {$this->saudacao()} ({$this->localTime()})
        PROMPT;
    }

    protected function consultaTool(): ?object
    {
        return new ConsultarCreditoCltTool($this->lead);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    protected function creditContextExtras(array $c): string
    {
        if (($c['niche'] ?? null) !== 'clt') {
            return '';
        }

        $totais = $c['resumoGeral']['totais'] ?? [];

        return "\nPublico: CLT | Vinculos: ".(int) ($totais['vinculos'] ?? 0).' | Ativos: '.(int) ($totais['vinculosAtivos'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $t
     */
    protected function creditValuesBlock(array $c, array $t): string
    {
        if (($c['niche'] ?? null) !== 'clt') {
            return $this->inssDefaultCreditValuesBlock($c, $t);
        }

        $vinculos = $c['vinculos'] ?? [];
        if ($vinculos === []) {
            return "\nDados CLT: nenhum vinculo retornado.";
        }

        $parts = [];
        foreach ($vinculos as $vinculo) {
            $empresa = $vinculo['razaoSocial'] ?? 'Empresa';
            $status = ($vinculo['ativo'] ?? false) ? 'ativo' : 'inativo';
            $salario = isset($vinculo['ultimoSalario']) && $vinculo['ultimoSalario'] !== null
                ? ' ultimo salario '.$this->brl((float) $vinculo['ultimoSalario'])
                : '';
            $parts[] = "{$empresa} ({$status}{$salario})";
        }

        return "\nDados CLT (use EXATAMENTE): ".implode(' | ', $parts);
    }
}
