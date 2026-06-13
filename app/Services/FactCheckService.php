<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class FactCheckService
{
    /**
     * Verifica se os valores que a IA está informando realmente existem no JSON do banco
     * Retorna uma string de correção caso falhe, ou null se estiver OK.
     */
    public function validateAgentResponse(Lead $lead, string $response): ?string
    {
        // Se o lead ainda não tem valores na memória, não há o que checar rigorosamente
        if (empty($lead->credito_json['resumoGeral']['totais'])) {
            return null;
        }

        $totais = $lead->credito_json['resumoGeral']['totais'];
        $beneficio = $lead->credito_json['beneficios'][0] ?? [];
        $produtos = $beneficio['produtos'] ?? [];

        // Puxar todos os valores disponíveis como limite do fact-check
        // Valores liberados/totais: margemLivre (Novo), refinanciamento, cartoes, totalEstimado
        $valoresValidos = [];
        if (($totais['margemLivre'] ?? 0) > 0) {
            $valoresValidos[] = (float) $totais['margemLivre'];
        }
        if (($totais['refinanciamento'] ?? 0) > 0) {
            $valoresValidos[] = (float) $totais['refinanciamento'];
        }
        if (($totais['cartoes'] ?? 0) > 0) {
            $valoresValidos[] = (float) $totais['cartoes'];
        }
        if (($totais['totalEstimado'] ?? 0) > 0) {
            $valoresValidos[] = (float) $totais['totalEstimado'];
        }

        // Valores de parcela mensal e liberado (desconto no benefício) — distintos dos totais
        $novo = $produtos['emprestimoNovo'] ?? [];
        if (($novo['parcelaMensal'] ?? 0) > 0) {
            $valoresValidos[] = (float) $novo['parcelaMensal'];
        }
        if (($novo['valorLiberado'] ?? 0) > 0) {
            $valoresValidos[] = (float) $novo['valorLiberado'];
        }
        foreach ($produtos['cartoes'] ?? [] as $cartao) {
            if (($cartao['parcelaMensal'] ?? 0) > 0) {
                $valoresValidos[] = (float) $cartao['parcelaMensal'];
            }
            if (($cartao['valorSaque'] ?? 0) > 0) {
                $valoresValidos[] = (float) $cartao['valorSaque'];
            }
        }
        foreach ($produtos['refinanciamento']['contratos'] ?? [] as $contrato) {
            if (($contrato['valorParcela'] ?? 0) > 0) {
                $valoresValidos[] = (float) $contrato['valorParcela'];
            }
            if (($contrato['valorLiberado'] ?? 0) > 0) {
                $valoresValidos[] = (float) $contrato['valorLiberado'];
            }
        }
        foreach ($produtos['portabilidade']['contratos'] ?? [] as $contrato) {
            if (($contrato['valorParcela'] ?? 0) > 0) {
                $valoresValidos[] = (float) $contrato['valorParcela'];
            }
        }

        // Prazo em parcelas (Novo e Refinanciamento: 96x) — só validar quando o cliente perguntar
        $prazoNovoRefin = 96;
        if (($totais['margemLivre'] ?? 0) > 0 || ($totais['refinanciamento'] ?? 0) > 0) {
            $valoresValidos[] = (float) $prazoNovoRefin;
        }

        if (empty($valoresValidos)) {
            return null;
        }

        // Tentar extrair valores monetários e prazos na string de resposta
        // Monetários: "R$ 1.500,00", "R$1500", "1.500 reais", "20 mil"
        // Prazo: "96 parcelas", "96x", "em 96x"
        $regex = '/(?:R\$|RS)\s*([\d\.,]+)|([\d\.,]+)\s*(?:reais|mil)|([\d]+)\s*(?:parcelas|x\b)/i';

        preg_match_all($regex, $response, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            // Pegar o valor numérico cru da captura 1, 2 ou 3 (prazo)
            $rawNumber = $match[1] ?? $match[2] ?? $match[3] ?? null;
            if ($rawNumber === null) {
                continue;
            }

            // Converter formato brasileiro (1.500,42) para float gringo (1500.42)
            $cleanNumber = str_replace('.', '', $rawNumber);
            $cleanNumber = str_replace(',', '.', $cleanNumber);
            $valorFalado = (float) $cleanNumber;

            // Especial: se falou "mil", multiplicar
            if (stripos($match[0], 'mil') !== false) {
                // Se for "2.5 mil", a conversão acima virou 2.5
                $valorFalado = $valorFalado * 1000;
            }

            // Ignorar valores triviais de exemplos
            if ($valorFalado < 50) {
                continue;
            }

            // Validar de forma cruzada com tolerância de R$ 5,00
            $aprovado = false;
            foreach ($valoresValidos as $valido) {
                if (abs($valorFalado - $valido) <= 5.0) {
                    $aprovado = true;
                    break;
                }
            }

            if (! $aprovado) {
                Log::warning('aria.fact_check_failed', [
                    'lead_id' => $lead->id,
                    'valor_falado' => $valorFalado,
                    'totais_validos' => $valoresValidos,
                    'resposta_crua' => $response,
                ]);

                $validList = implode(', ', array_map(
                    fn (float $v) => 'R$ '.number_format($v, 2, ',', '.'),
                    $valoresValidos
                ));

                return 'ERRO: R$ '.number_format($valorFalado, 2, ',', '.')." não existe. Valores válidos: {$validList}. Reescreva a mensagem usando APENAS estes valores.";
            }
        }

        return null; // OK
    }
}
