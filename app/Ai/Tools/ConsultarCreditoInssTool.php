<?php

namespace App\Ai\Tools;

use App\Models\AgentOperationalRule;
use App\Services\CreditoQualificacaoService;
use Stringable;

class ConsultarCreditoInssTool extends AbstractConsultaCreditoTool
{
    public function description(): Stringable|string
    {
        return 'Consulta crédito INSS pelo CPF. Chamada automática ao receber CPF.';
    }

    protected function nicheLabel(): string
    {
        return 'INSS';
    }

    protected function audienceWord(): string
    {
        return 'cliente';
    }

    protected function circuitSlug(): string
    {
        return 'inss';
    }

    protected function webhookConfigKey(): string
    {
        return 'services.credflow.webhook_consulta';
    }

    protected function webhookEnvHint(): string
    {
        return 'TENAZ_WEBHOOK_CONSULTA';
    }

    protected function toolName(): string
    {
        return 'consultar_credito_inss';
    }

    /**
     * @param  array<string, mixed>  $rawData
     * @param  \Illuminate\Support\Collection<int, AgentOperationalRule>  $rules
     * @return array<string, mixed>
     */
    protected function qualify(array $rawData, $rules): array
    {
        return (new CreditoQualificacaoService($rules))->qualificar($rawData);
    }

    /**
     * Format credito_json (ARIA schema) as the exact text the agent receives from the tool.
     * Public so Laboratory can display "payload enviado ao agente" for validation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function formatPayloadForAgent(array $data): string
    {
        $status = $data['status'] ?? 'DESCONHECIDO';
        $nome = $data['cliente']['nome'] ?? 'Cliente';
        $idade = $data['cliente']['idade'] ?? '?';
        $totais = $data['resumoGeral']['totais'] ?? [];
        $desbloqueio = ! empty($data['resumoGeral']['precisaDesbloqueio']);

        $lines = ["CONSULTA: {$status}", "Cliente: {$nome} ({$idade} anos)"];

        if ($status === 'QUALIFICADO') {
            $beneficio = $data['beneficios'][0] ?? [];
            $produtos = $beneficio['produtos'] ?? [];

            if (($totais['margemLivre'] ?? 0) > 0) {
                $novo = $produtos['emprestimoNovo'] ?? [];
                $vl = self::brl($novo['valorLiberado'] ?? $totais['margemLivre']);
                $pc = self::brl($novo['parcelaMensal'] ?? 0);
                $lines[] = "Novo: {$vl} liberado — parcela {$pc}/mês (desconto no benefício)";
            }
            if (($totais['refinanciamento'] ?? 0) > 0) {
                $lines[] = 'Refinanciamento: '.self::brl($totais['refinanciamento']).' de troco (não desconta do salário — você recebe o dinheiro em mãos)';
            }
            if (($totais['cartoes'] ?? 0) > 0) {
                $cartoes = $produtos['cartoes'] ?? [];
                $parts = [];
                foreach ($cartoes as $c) {
                    $saque = $c['valorSaque'] ? self::brl($c['valorSaque']) : 'a calcular';
                    $pc = ($c['parcelaMensal'] ?? 0) > 0 ? ' — parcela '.self::brl($c['parcelaMensal']).'/mês' : '';
                    $parts[] = "{$c['tipo']}: {$saque}{$pc}";
                }
                $lines[] = 'Cartões: '.implode(' | ', $parts);
            }

            $lines[] = 'Total estimado: '.self::brl($totais['totalEstimado'] ?? 0);

            if (($totais['margemLivre'] ?? 0) > 0 || ($totais['refinanciamento'] ?? 0) > 0) {
                $lines[] = 'Prazo Novo/Refin: 96x (informar só se perguntarem)';
            }

            if ($desbloqueio) {
                $lines[] = '⚠ Desbloqueio de empréstimo necessário.';
            }
        } elseif ($status === 'DESQUALIFICADO') {
            $motivos = $data['beneficios'][0]['qualificacao']['motivos'] ?? [];
            $lines[] = 'Motivo: '.(implode('; ', $motivos) ?: 'regras do corretor');
        } else {
            $lines[] = 'Nenhum produto atingiu o limiar mínimo.';
        }

        return implode("\n", $lines);
    }

    /**
     * Build a summary of credito_json for Laboratory validation: valorParcela vs valorTotal per product, Refin vs Portab XOR.
     * Public so Laboratory can display a "ground truth" panel next to {@see self::formatPayloadForAgent}.
     *
     * @param  array<string, mixed>  $creditoJson
     * @return array{products: array<int, array{name: string, valor_total: float|null, valor_parcela: float|null, note: string|null}>, refin_vs_portab_note: string}
     */
    public static function buildGroundTruthSummary(array $creditoJson): array
    {
        $products = [];
        $totais = $creditoJson['resumoGeral']['totais'] ?? [];
        $beneficio = $creditoJson['beneficios'][0] ?? [];
        $produtos = $beneficio['produtos'] ?? [];

        $novo = $produtos['emprestimoNovo'] ?? [];
        if (($totais['margemLivre'] ?? 0) > 0) {
            $products[] = [
                'name' => 'Crédito Novo',
                'valor_total' => (float) ($novo['valorLiberado'] ?? $totais['margemLivre'] ?? 0),
                'valor_parcela' => (float) ($novo['parcelaMensal'] ?? 0) ?: null,
                'note' => 'valor_total = valor liberado em mãos; valor_parcela = desconto mensal no benefício',
            ];
        }

        $refin = $produtos['refinanciamento'] ?? [];
        $totalRefin = (float) ($refin['totalLiberado'] ?? $totais['refinanciamento'] ?? 0);
        $products[] = [
            'name' => 'Refinanciamento',
            'valor_total' => $totalRefin ?: null,
            'valor_parcela' => null,
            'note' => 'Troco (não gera parcela nova). XOR com Portabilidade — mesmo contrato, um ou outro.',
        ];

        $port = $produtos['portabilidade'] ?? [];
        $totalPort = (float) ($port['totalParcelas'] ?? $totais['portabilidade'] ?? 0);
        $products[] = [
            'name' => 'Portabilidade',
            'valor_total' => null,
            'valor_parcela' => $totalPort ?: null,
            'note' => 'Redução de parcela (não libera troco). XOR com Refinanciamento.',
        ];

        $cartoes = $produtos['cartoes'] ?? [];
        foreach ($cartoes as $cartao) {
            $products[] = [
                'name' => $cartao['tipo'] ?? 'Cartão',
                'valor_total' => isset($cartao['valorSaque']) ? (float) $cartao['valorSaque'] : null,
                'valor_parcela' => isset($cartao['parcelaMensal']) && $cartao['parcelaMensal'] > 0 ? (float) $cartao['parcelaMensal'] : null,
                'note' => 'valor_total = saque; valor_parcela = desconto mensal',
            ];
        }

        return [
            'products' => $products,
            'refin_vs_portab_note' => 'Refinanciamento e Portabilidade usam os mesmos contratos: o cliente escolhe UM ou OUTRO (XOR). Refin = mesmo banco, recebe troco; Portab = troca de banco, reduz parcela.',
        ];
    }
}
