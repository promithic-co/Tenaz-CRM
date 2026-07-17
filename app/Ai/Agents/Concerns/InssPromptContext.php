<?php

namespace App\Ai\Agents\Concerns;

use App\Models\AgentOperationalRule;

/**
 * INSS/consignado prompt context shared by the CredFlow family of agents
 * (CredFlow, Bulk, FollowUp, Siape, Clt). Holds everything credit-specific
 * that used to live on BaseCustomerServiceAgent: status hints tied to the
 * INSS pipeline, corretor rule variables and the credito_json lead context.
 *
 * The base class stays niche-agnostic so GenericAgent (and future niches)
 * never inherit INSS wording.
 */
trait InssPromptContext
{
    /**
     * Next-action hint for the INSS pipeline statuses.
     */
    protected function buildStatusHint(): string
    {
        $docs = array_filter($this->lead->documentos_coletados ?? []);
        $docsCount = count($docs);

        return match ($this->lead->status) {
            'novo' => "\n→ PRÓXIMO PASSO: solicite o CPF se ainda não tiver.",
            'qualificado' => match (true) {
                $docsCount >= 3 => "\n→ PRÓXIMO PASSO: documentação completa — acione `escalar_para_humano`.",
                $docsCount > 0 => "\n→ PRÓXIMO PASSO: continue coletando documentos (já recebidos: {$docsCount}). Não reapresente as ofertas.",
                default => "\n→ PRÓXIMO PASSO: apresente as ofertas disponíveis e inicie a coleta de documentos.",
            },
            'sem_credito' => "\n→ PRÓXIMO PASSO: acione `registrar_lead_sem_credito` se o cliente confirmar interesse futuro.",
            'desqualificado' => "\n→ PRÓXIMO PASSO: explique que os critérios mínimos do corretor não foram atingidos. Não há produto disponível no momento.",
            'escalado' => "\n→ LEAD JÁ ESCALADO: não acione `escalar_para_humano` novamente. Aguarde o especialista assumir.",
            default => '',
        };
    }

    /**
     * Shared variables plus the corretor rule values used by INSS templates.
     *
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    protected function buildPromptVariables(?int $userId, array $cfg): array
    {
        return array_merge(
            parent::buildPromptVariables($userId, $cfg),
            $this->corretorRuleVariables($userId),
        );
    }

    /**
     * Corretor minimum/eligibility rule variables for prompt rendering.
     *
     * @return array<string, mixed>
     */
    protected function corretorRuleVariables(?int $userId): array
    {
        $rules = $userId
            ? AgentOperationalRule::forUser($userId)
            : AgentOperationalRule::forTenant($this->lead->tenant_id ?? 'default');

        return [
            'min_novo' => number_format((float) $rules->regra('valor_minimo_liberado_novo'), 0, ',', '.'),
            'min_refin' => number_format((float) $rules->regra('valor_minimo_liberado_refin'), 0, ',', '.'),
            'min_parcela_port' => number_format((float) $rules->regra('valor_minimo_parcela_portabilidade'), 0, ',', '.'),
            'perc_min_port' => (int) round((float) $rules->regra('percentual_minimo_pago_portabilidade') * 100),
            'idade_max' => (int) $rules->regra('idade_maxima'),
        ];
    }

    /**
     * Credit values and collected documents appended to the lead context block.
     */
    protected function nicheLeadContext(): string
    {
        $ctx = '';

        if ($this->lead->credito_json) {
            $c = $this->lead->credito_json;
            $ctx .= $this->creditContextExtras($c);

            $t = $c['resumoGeral']['totais'] ?? [];
            if (! empty($t)) {
                $ctx .= $this->creditValuesBlock($c, $t);
            } elseif (! empty($c['status'])) {
                $ctx .= "\nCrédito: {$c['status']}";
            }
        }

        if ($this->lead->documentos_coletados) {
            $coletados = array_keys(array_filter($this->lead->documentos_coletados));
            if (! empty($coletados)) {
                $ctx .= "\nDocs: ".implode(', ', $coletados);
            }
        }

        return $ctx;
    }

    /**
     * Niche-specific context appended right after the identity line and before the
     * credit-values block (e.g. SIAPE órgão/matrícula/renda). Default: none.
     *
     * @param  array<string, mixed>  $c  Decoded credito_json
     */
    protected function creditContextExtras(array $c): string
    {
        return '';
    }

    /**
     * Format the "Valores (use EXATAMENTE)" credit block. Default is the INSS
     * benefício-based shape; niche agents override for their product layout.
     *
     * @param  array<string, mixed>  $c  Decoded credito_json
     * @param  array<string, mixed>  $t  resumoGeral.totais
     */
    protected function creditValuesBlock(array $c, array $t): string
    {
        $ctx = "\nValores (use EXATAMENTE):";
        if (($t['margemLivre'] ?? 0) > 0) {
            $parcela = $c['beneficios'][0]['produtos']['emprestimoNovo']['parcelaMensal'] ?? 0;
            $ctx .= " Novo=libera {$this->brl($t['margemLivre'])} (parcela {$this->brl($parcela)}/mês)";
        }
        if (($t['refinanciamento'] ?? 0) > 0) {
            $ctx .= " | Refin=troco de {$this->brl($t['refinanciamento'])} (sem parcela nova)";
        }
        if (($t['cartoes'] ?? 0) > 0) {
            $cartoes = $c['beneficios'][0]['produtos']['cartoes'] ?? [];
            $partes = [];
            foreach ($cartoes as $cartao) {
                $tipo = $cartao['tipo'] ?? 'Cartão';
                $pc = ($cartao['parcelaMensal'] ?? 0) > 0 ? " parcela {$this->brl($cartao['parcelaMensal'])}/mês" : '';
                $saque = ($cartao['valorSaque'] ?? null) ? " saque {$this->brl((float) $cartao['valorSaque'])}" : '';
                $partes[] = "{$tipo}:{$saque}{$pc}";
            }
            $cartaoStr = ! empty($partes) ? ' ('.implode(' | ', $partes).')' : '';
            $ctx .= " | Cartões={$this->brl($t['cartoes'])}{$cartaoStr}";
        }
        $ctx .= " | Total={$this->brl($t['totalEstimado'] ?? 0)}";

        return $ctx;
    }
}
