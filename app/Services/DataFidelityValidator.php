<?php

namespace App\Services;

class DataFidelityValidator
{
    private const PASS_THRESHOLD = 90.0;

    private const SEVERITY_PENALTIES = [
        'critical' => 25,
        'high' => 15,
        'medium' => 5,
        'low' => 1,
    ];

    public function validate(string $agentResponse, array $creditoJson): FidelityReport
    {
        $hallucinations = [];
        $matches = [];
        $score = 100.0;

        // 1. Status fidelity (weight: 30%)
        $statusResult = $this->validateStatus($agentResponse, $creditoJson);
        $statusCorrect = $statusResult['correct'];

        if (! $statusCorrect) {
            $hallucinations[] = $statusResult['hallucination'];
            $score -= self::SEVERITY_PENALTIES[$statusResult['hallucination']['severity']];
        }

        // 2. Monetary value fidelity (weight: 50%)
        // Use greedy exclusive assignment: build all (field, agentValue) pairs within ±30%,
        // sort by deviation ascending, assign each agent value to its closest ground truth
        // exclusively. Un-matched ground truth fields were simply not mentioned (no penalty).
        $groundTruthValues = $this->extractGroundTruthValues($creditoJson);
        $agentValues = $this->extractBrlValues($agentResponse);

        $candidates = [];
        foreach ($groundTruthValues as $field => $expectedValue) {
            if ($expectedValue <= 0) {
                continue;
            }

            foreach ($agentValues as $agentValue) {
                $deviation = abs($agentValue - $expectedValue);
                $deviationPct = ($deviation / $expectedValue) * 100;

                if ($deviationPct <= 30.0) {
                    $candidates[] = [
                        'field' => $field,
                        'expected' => $expectedValue,
                        'actual' => $agentValue,
                        'deviation' => $deviation,
                        'deviationPct' => $deviationPct,
                    ];
                }
            }
        }

        // Sort by deviation ascending so best matches are assigned first
        usort($candidates, fn ($a, $b) => $a['deviation'] <=> $b['deviation']);

        $assignedFields = [];
        $usedAgentValues = [];

        foreach ($candidates as $candidate) {
            if (in_array($candidate['field'], $assignedFields, true)) {
                continue;
            }
            if (in_array($candidate['actual'], $usedAgentValues, true)) {
                continue;
            }

            $assignedFields[] = $candidate['field'];
            $usedAgentValues[] = $candidate['actual'];

            if ($candidate['deviation'] <= 0.50) {
                $matches[] = ['field' => $candidate['field'], 'value' => $candidate['expected']];
            } elseif ($candidate['deviationPct'] > 10) {
                $hallucinations[] = [
                    'field' => $candidate['field'],
                    'expected' => $this->formatBrl($candidate['expected']),
                    'actual' => $this->formatBrl($candidate['actual']),
                    'severity' => 'critical',
                ];
                $score -= self::SEVERITY_PENALTIES['critical'];
            } elseif ($candidate['deviationPct'] > 5) {
                $hallucinations[] = [
                    'field' => $candidate['field'],
                    'expected' => $this->formatBrl($candidate['expected']),
                    'actual' => $this->formatBrl($candidate['actual']),
                    'severity' => 'high',
                ];
                $score -= self::SEVERITY_PENALTIES['high'];
            } else {
                $hallucinations[] = [
                    'field' => $candidate['field'],
                    'expected' => $this->formatBrl($candidate['expected']),
                    'actual' => $this->formatBrl($candidate['actual']),
                    'severity' => 'medium',
                ];
                $score -= self::SEVERITY_PENALTIES['medium'];
            }
        }

        // 3. Product availability fidelity (weight: 20%)
        $productResult = $this->validateProductAvailability($agentResponse, $creditoJson);
        foreach ($productResult['hallucinations'] as $h) {
            $hallucinations[] = $h;
            $score -= self::SEVERITY_PENALTIES[$h['severity']];
        }

        $score = max(0.0, $score);

        return new FidelityReport(
            score: round($score, 2),
            hallucinations: $hallucinations,
            matches: $matches,
            statusCorrect: $statusCorrect,
            passed: $score >= self::PASS_THRESHOLD,
        );
    }

    private function validateStatus(string $agentResponse, array $creditoJson): array
    {
        $status = $creditoJson['status'] ?? 'DESQUALIFICADO';
        $responseLower = mb_strtolower($agentResponse);

        $qualificadoKeywords = ['crédito', 'credito', 'valor liberado', 'parcela', 'disponível', 'disponivel', 'empréstimo', 'emprestimo'];
        $negativeKeywords = ['não temos', 'nao temos', 'sem opções', 'sem opcoes', 'não há', 'nao ha', 'infelizmente', 'não possui', 'nao possui'];

        $mentionsCredit = false;
        foreach ($qualificadoKeywords as $kw) {
            if (str_contains($responseLower, $kw)) {
                $mentionsCredit = true;
                break;
            }
        }

        $mentionsNegative = false;
        foreach ($negativeKeywords as $kw) {
            if (str_contains($responseLower, $kw)) {
                $mentionsNegative = true;
                break;
            }
        }

        if ($status === 'QUALIFICADO' && $mentionsNegative && ! $mentionsCredit) {
            return [
                'correct' => false,
                'hallucination' => [
                    'field' => 'status_qualificacao',
                    'expected' => 'QUALIFICADO — agent should present credit offers',
                    'actual' => 'Agent said no options available',
                    'severity' => 'critical',
                ],
            ];
        }

        if (in_array($status, ['DESQUALIFICADO', 'SEM_CREDITO']) && $mentionsCredit && ! $mentionsNegative) {
            return [
                'correct' => false,
                'hallucination' => [
                    'field' => 'status_qualificacao',
                    'expected' => $status.' — agent should NOT present credit offers',
                    'actual' => 'Agent presented credit offers for non-qualified client',
                    'severity' => 'critical',
                ],
            ];
        }

        return ['correct' => true, 'hallucination' => null];
    }

    private function extractGroundTruthValues(array $creditoJson): array
    {
        $values = [];
        $totais = $creditoJson['resumoGeral']['totais'] ?? [];
        $beneficios = $creditoJson['beneficios'] ?? [];

        // Collect product-level values first — these are the atomic facts the agent must report.
        // Aggregate totals (margem_livre, cartoes_total) are derived sums that agents rarely cite
        // verbatim; they are only added when no component product value exists.
        $hasEmprestimoNovo = false;
        $hasCartoes = false;

        foreach ($beneficios as $beneficio) {
            $produtos = $beneficio['produtos'] ?? [];

            $emprestimoNovo = $produtos['emprestimoNovo'] ?? [];
            if (! empty($emprestimoNovo['disponivel'])) {
                if (isset($emprestimoNovo['valorLiberado']) && $emprestimoNovo['valorLiberado'] > 0) {
                    $values['emprestimo_novo_valor'] = (float) $emprestimoNovo['valorLiberado'];
                    $hasEmprestimoNovo = true;
                }
                if (isset($emprestimoNovo['parcelaMensal']) && $emprestimoNovo['parcelaMensal'] > 0) {
                    $values['emprestimo_novo_parcela'] = (float) $emprestimoNovo['parcelaMensal'];
                }
            }

            $cartoes = $produtos['cartoes'] ?? [];
            foreach ($cartoes as $idx => $cartao) {
                $tipo = $cartao['tipo'] ?? "cartao_{$idx}";
                if (isset($cartao['valorSaque']) && $cartao['valorSaque'] > 0) {
                    $values["cartao_{$tipo}_valor"] = (float) $cartao['valorSaque'];
                    $hasCartoes = true;
                }
                // Card parcelas are small values often not cited — skip to avoid false positives
            }
        }

        // Only include aggregate totals when no atomic product values cover them
        if (! $hasEmprestimoNovo && isset($totais['margemLivre']) && $totais['margemLivre'] > 0) {
            $values['margem_livre'] = (float) $totais['margemLivre'];
        }

        if (! $hasCartoes && isset($totais['cartoes']) && $totais['cartoes'] > 0) {
            $values['cartoes_total'] = (float) $totais['cartoes'];
        }

        if (isset($totais['refinanciamento']) && $totais['refinanciamento'] > 0) {
            $values['refinanciamento_total'] = (float) $totais['refinanciamento'];
        }

        // totalEstimado is a key figure agents should cite — always include it
        if (isset($totais['totalEstimado']) && $totais['totalEstimado'] > 0) {
            $values['total_estimado'] = (float) $totais['totalEstimado'];
        }

        return $values;
    }

    private function validateProductAvailability(string $agentResponse, array $creditoJson): array
    {
        $hallucinations = [];
        $responseLower = mb_strtolower($agentResponse);
        $beneficios = $creditoJson['beneficios'][0]['produtos'] ?? [];

        $refinanciamento = $beneficios['refinanciamento'] ?? [];
        $totalLiberado = $refinanciamento['totalLiberado'] ?? 0;
        if ($totalLiberado <= 0) {
            $refinKeywords = ['refinanciamento', 'portabilidade'];
            foreach ($refinKeywords as $kw) {
                if (str_contains($responseLower, $kw)) {
                    $hallucinations[] = [
                        'field' => 'refinanciamento',
                        'expected' => 'R$ 0,00 (não disponível)',
                        'actual' => 'Agent mentioned refinanciamento which does not exist',
                        'severity' => 'high',
                    ];
                    break;
                }
            }
        }

        return ['hallucinations' => $hallucinations];
    }

    private function extractBrlValues(string $text): array
    {
        preg_match_all('/R\$\s*([\d.,]+)/', $text, $matches);
        $values = [];

        foreach ($matches[1] as $match) {
            $values[] = $this->parseBrl($match);
        }

        return array_filter($values, fn ($v) => $v > 10);
    }

    private function parseBrl(string $value): float
    {
        // Handle Brazilian format: 1.234,56 → 1234.56 and 1.234 → 1234
        // Strip thousand-separator dots: dot followed by exactly 3 digits then comma, non-digit, or end
        $cleaned = preg_replace('/\.(\d{3})(?=[,\D]|$)/', '$1', $value);
        $cleaned = str_replace(',', '.', $cleaned);

        return (float) $cleaned;
    }

    private function formatBrl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
