<?php

namespace App\Services;

use App\Models\AgentOperationalRule;

/**
 * Porta determinística da lógica JavaScript do n8n (consultar_credito_inss Transform node).
 * Aplica qualificação por produto com limiares e bancos configuráveis por corretor.
 *
 * As regras são recebidas via AgentOperationalRule e substituem os valores hardcoded:
 *  - Empréstimo novo:  valorLiberadoMargem > regras_globais.valor_minimo_liberado_novo
 *  - Refinanciamento:  valorLiberado por contrato > regras_globais.valor_minimo_liberado_refin
 *                      E banco do contrato habilitado pelo corretor para 'refin'
 *  - Portabilidade:    parcela > regras_globais.valor_minimo_parcela_portabilidade
 *                      E ≥ percentual_minimo_pago_portabilidade pago
 *                      E banco do contrato habilitado pelo corretor para 'port'
 *  - Cartões RMC/RCC:  margem disponível > 0 E banco habilitado para 'rmc'/'rcc'
 *
 * Regra XOR: refin e port operam nos mesmos contratos — não se somam no totalEstimado.
 */
class CreditoQualificacaoService
{
    private ?AgentOperationalRule $rules = null;

    public function __construct(?AgentOperationalRule $rules = null)
    {
        $this->rules = $rules;
    }

    // -------------------------------------------------------------------------
    // Helpers que lêem as regras (com fallback nos defaults do model)
    // -------------------------------------------------------------------------

    private function r(string $key): mixed
    {
        if ($this->rules) {
            return $this->rules->regra($key);
        }
        return AgentOperationalRule::$REGRAS_GLOBAIS_PADRAO[$key] ?? null;
    }

    private function e(string $key): bool
    {
        if ($this->rules) {
            return $this->rules->especie($key);
        }
        return AgentOperationalRule::$REGRAS_ESPECIES_PADRAO[$key] ?? false;
    }

    /** Retorna true se o banco está habilitado para o produto no corretor. */
    private function bancoAtivo(string $nomeBanco, string $produto): bool
    {
        if (!$this->rules) {
            return true; // sem regras: permite tudo (comportamento legado)
        }

        $siglasBancos = $this->rules->bancosAtivosParaProduto($produto);

        if (empty($siglasBancos)) {
            return false;
        }

        $nomeUpper = strtoupper($nomeBanco);

        // Verifica correspondência parcial (ex: "SANTANDER (OLE)" → "SANTANDER" ou "OLE")
        foreach ($siglasBancos as $sigla) {
            if (str_contains($nomeUpper, $sigla)) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Qualificação principal
    // -------------------------------------------------------------------------

    public function qualificar(array $consulta): array
    {
        // 1. Extração de campos brutos
        $dadosBeneficio     = $consulta['BENEFICIO'] ?? [];
        $contratos          = $consulta['CONTRATO']  ?? [];
        $nomeCompleto       = $consulta['NOME']       ?? 'Cliente';
        $primeiroNome       = explode(' ', $nomeCompleto)[0];
        $cpf                = $consulta['FULL_CPF']  ?? '';
        $idade              = (int) ($consulta['IDADE'] ?? 0);
        $especieDescricao   = $consulta['ESP']        ?? '';
        $especieCodigo      = (int) ($consulta['ESP_Codigo'] ?? 0);
        $especieConsignavel = strtoupper($consulta['ESP_Consignavel'] ?? '');
        $situacaoBeneficio  = strtoupper($dadosBeneficio['situacao']  ?? '');
        $bloqueioEmprestimo = ($dadosBeneficio['bloqemp'] ?? '') === 'Sim';
        $numeroBeneficio    = $dadosBeneficio['nb'] ?? '';

        $valorMR = 0.0;
        if (!empty($consulta['MR']))                   $valorMR = (float) $consulta['MR'];
        elseif (!empty($dadosBeneficio['valormr']))    $valorMR = (float) $dadosBeneficio['valormr'];
        elseif (!empty($dadosBeneficio['vlbasecalc'])) $valorMR = (float) $dadosBeneficio['vlbasecalc'];

        // 2. Qualificação global
        $motivosDesqualificacao = [];
        $idadeMin = (int) $this->r('idade_minima');
        $idadeMax = (int) $this->r('idade_maxima');

        if ($situacaoBeneficio !== 'ATIVO') {
            $motivosDesqualificacao[] = "Benefício não está ATIVO (Status: " . ($dadosBeneficio['situacao'] ?? 'desconhecido') . ")";
        }
        if ($especieConsignavel !== 'S') {
            $motivosDesqualificacao[] = 'Espécie de benefício não consignável';
        }
        if ($idade > $idadeMax) {
            $motivosDesqualificacao[] = "Idade superior a {$idadeMax} anos ({$idade})";
        }
        if ($idade < $idadeMin) {
            $motivosDesqualificacao[] = "Idade inferior a {$idadeMin} anos ({$idade})";
        }

        // Regras de espécies sensíveis
        $especiesInvalidez = [4, 5, 6, 32, 33, 34, 51, 83, 92];
        $especiesLoas      = [87, 88];

        if (in_array($especieCodigo, $especiesInvalidez) && $idade < 60 && !$this->e('aceita_invalidez_abaixo_60')) {
            $motivosDesqualificacao[] = "Invalidez com idade abaixo de 60 anos não habilitada pelo corretor";
        }
        if (in_array($especieCodigo, $especiesLoas) && !$this->e('aceita_loas_emprestimo')) {
            $motivosDesqualificacao[] = "LOAS (espécie {$especieCodigo}) não habilitado para empréstimo pelo corretor";
        }

        $isDesqualificado = count($motivosDesqualificacao) > 0;

        // 3. Empréstimo Novo
        $margemLivre       = (float) ($dadosBeneficio['MargemCalculada']     ?? 0);
        $valorLiberadoNovo = (float) ($dadosBeneficio['ValorLiberadoMargem'] ?? 0);
        $minNovo           = (float) $this->r('valor_minimo_liberado_novo');

        $emprestimoNovo = [
            'disponivel'    => !$isDesqualificado && $margemLivre > 0 && $valorLiberadoNovo > $minNovo,
            'parcelaMensal' => $margemLivre,
            'valorLiberado' => $valorLiberadoNovo,
            'resumo'        => (!$isDesqualificado && $valorLiberadoNovo > $minNovo)
                ? "Margem livre de R$ " . number_format($margemLivre, 2, '.', '') . " liberando aprox. R$ " . number_format($valorLiberadoNovo, 2, '.', '')
                : ($valorLiberadoNovo > 0
                    ? "Saldo de R$ " . number_format($valorLiberadoNovo, 2, '.', '') . " abaixo do mínimo (R$ " . number_format($minNovo, 2, '.', '') . ")"
                    : "Sem margem livre disponível"),
        ];

        // 4. Refinanciamento
        $contratosRefin       = [];
        $totalRefinanciamento = 0.0;
        $minRefin             = (float) $this->r('valor_minimo_liberado_refin');

        foreach ($contratos as $contrato) {
            $valorLiberado = (float) ($contrato['ValorLiberado'] ?? 0);
            $parcPagas     = (int)   ($contrato['ParcPagas']    ?? 0);
            $vlParcela     = (float) ($contrato['Vl_Parcela']   ?? 0);
            $nomeBanco     = $contrato['Banco_Nome'] ?? '';

            if ($valorLiberado > $minRefin && $this->bancoAtivo($nomeBanco, 'refin')) {
                $totalRefinanciamento += $valorLiberado;
                $contratosRefin[] = [
                    'banco'         => $nomeBanco,
                    'valorParcela'  => $vlParcela,
                    'parcelasPagas' => $parcPagas,
                    'valorLiberado' => $valorLiberado,
                    'resumo'        => "Banco {$nomeBanco}: Parcela R$ " . number_format($vlParcela, 2, '.', '') . " (Pagas: {$parcPagas}) libera aprox. R$ " . number_format($valorLiberado, 2, '.', ''),
                ];
            }
        }

        $refinanciamento = [
            'contratos'    => $contratosRefin,
            'totalLiberado' => round($totalRefinanciamento, 2),
        ];

        // 5. Portabilidade
        $contratosPort     = [];
        $totalParcelasPort = 0.0;
        $minParcelaPort    = (float) $this->r('valor_minimo_parcela_portabilidade');
        $minPercPago       = (float) $this->r('percentual_minimo_pago_portabilidade');

        foreach ($contratos as $contrato) {
            $vlParcela = (float) ($contrato['Vl_Parcela'] ?? 0);
            $parcPagas = (int)   ($contrato['ParcPagas']  ?? 0);
            $prazo     = (int)   ($contrato['Prazo']      ?? 0);
            $percPago  = $prazo > 0 ? $parcPagas / $prazo : 0;
            $nomeBanco = $contrato['Banco_Nome'] ?? '';

            if (!$isDesqualificado && $vlParcela > $minParcelaPort && $percPago >= $minPercPago && $this->bancoAtivo($nomeBanco, 'port')) {
                $totalParcelasPort += $vlParcela;
                $contratosPort[] = [
                    'banco'          => $nomeBanco,
                    'valorParcela'   => $vlParcela,
                    'parcelasPagas'  => $parcPagas,
                    'prazoTotal'     => $prazo,
                    'percentualPago' => (int) round($percPago * 100),
                    'resumo'         => "Banco {$nomeBanco}: Parcela R$ " . number_format($vlParcela, 2, '.', '') . " (" . (int) round($percPago * 100) . "% pago) — elegível para portabilidade",
                ];
            }
        }

        $portabilidade = [
            'contratos'    => $contratosPort,
            'totalParcelas' => round($totalParcelasPort, 2),
        ];

        // 6. Cartões RMC / RCC
        $margemRMC = (float) ($dadosBeneficio['margemdispcartao']    ?? 0);
        $margemRCC = (float) ($dadosBeneficio['margemdispcartaoBen'] ?? 0);
        $cartoes   = [];

        // LOAS: verificar se cartão está habilitado
        $loasCartaoPermitido = !in_array($especieCodigo, $especiesLoas) || $this->e('aceita_loas_cartao');

        if (!$isDesqualificado && $loasCartaoPermitido && $margemRMC > 0) {
            $rmcBancos = $this->rules ? $this->rules->bancosAtivosParaProduto('rmc') : [];
            if (empty($this->rules) || !empty($rmcBancos)) {
                $cartoes[] = $this->buildCardOffer('Cartão RMC', $margemRMC, $valorMR);
            }
        }
        if (!$isDesqualificado && $loasCartaoPermitido && $margemRCC > 0) {
            $rccBancos = $this->rules ? $this->rules->bancosAtivosParaProduto('rcc') : [];
            if (empty($this->rules) || !empty($rccBancos)) {
                $cartoes[] = $this->buildCardOffer('Cartão RCC', $margemRCC, $valorMR);
            }
        }

        // 7. Totais — XOR: refin e port não se somam (mesmos contratos)
        $qualMargemLivre     = $emprestimoNovo['disponivel'] ? round($valorLiberadoNovo, 2) : 0;
        $qualRefinanciamento = round($totalRefinanciamento, 2);
        $qualPortabilidade   = round($totalParcelasPort, 2);
        $totalCartoes        = array_reduce($cartoes, fn($sum, $c) => $sum + (is_float($c['valorSaque']) ? $c['valorSaque'] : 0.0), 0.0);

        $totalEstimado = round($qualMargemLivre + $qualRefinanciamento + $totalCartoes, 2);

        // 8. Status
        if ($isDesqualificado) {
            $status = 'DESQUALIFICADO';
        } elseif ($qualMargemLivre > 0 || $qualRefinanciamento > 0 || $qualPortabilidade > 0 || $totalCartoes > 0) {
            $status = 'QUALIFICADO';
        } else {
            $status = 'SEM_CREDITO';
        }

        // 9. Resumo textual
        if ($status === 'QUALIFICADO') {
            $produtos = [];
            if ($qualMargemLivre > 0)     $produtos[] = 'Novo';
            if ($qualRefinanciamento > 0) $produtos[] = 'Refin';
            if ($qualPortabilidade > 0)   $produtos[] = 'Port';
            if ($totalCartoes > 0)        $produtos[] = 'Cartão';

            $textoResumo = "{$nomeCompleto} ({$idade} anos, Renda: R$ " . number_format($valorMR, 2, ',', '.') . "). "
                . "QUALIFICADO [" . implode(', ', $produtos) . "]. "
                . "Total estimado: R$ " . number_format($totalEstimado, 2, ',', '.') . "."
                . ($bloqueioEmprestimo ? " Desbloqueio necessário." : "");
        } elseif ($status === 'SEM_CREDITO') {
            $textoResumo = "{$nomeCompleto} ({$idade} anos, Renda: R$ " . number_format($valorMR, 2, ',', '.') . "). "
                . "SEM_CREDITO — Benefício ativo mas nenhum produto atingiu limiar mínimo ou nenhum banco habilitado pelo corretor.";
        } else {
            $textoResumo = "{$nomeCompleto} ({$idade} anos, Renda: R$ " . number_format($valorMR, 2, ',', '.') . "). "
                . "DESQUALIFICADO — " . implode('; ', $motivosDesqualificacao) . ".";
        }

        // 10. Schema de saída
        return [
            'status'  => $status,
            'cliente' => [
                'nome'         => $nomeCompleto,
                'primeiroNome' => $primeiroNome,
                'cpf'          => $cpf,
                'idade'        => $idade,
            ],
            'resumoGeral' => [
                'qualificado'        => $status === 'QUALIFICADO',
                'precisaDesbloqueio' => $bloqueioEmprestimo,
                'totais'             => [
                    'margemLivre'     => $qualMargemLivre,
                    'refinanciamento' => $qualRefinanciamento,
                    'portabilidade'   => $qualPortabilidade,
                    'cartoes'         => round($totalCartoes, 2),
                    'totalEstimado'   => $totalEstimado,
                ],
                'textoResumo' => $textoResumo,
            ],
            'beneficios' => [[
                'numero'             => $numeroBeneficio,
                'especieDescricao'   => $especieDescricao,
                'rendaMensal'        => $valorMR,
                'precisaDesbloqueio' => $bloqueioEmprestimo,
                'qualificacao'       => [
                    'qualificado' => !$isDesqualificado,
                    'motivos'     => $motivosDesqualificacao,
                ],
                'produtos' => [
                    'emprestimoNovo'  => $emprestimoNovo,
                    'refinanciamento' => $refinanciamento,
                    'portabilidade'   => $portabilidade,
                    'cartoes'         => $cartoes,
                ],
            ]],
        ];
    }

    private function buildCardOffer(string $tipo, float $margem, float $mr): array
    {
        // Caso padrão com MR ~R$1621 (valor hardcoded no JS original)
        if (abs($mr - 1621) < 5) {
            return [
                'tipo'          => $tipo,
                'valorSaque'    => 1814.61,
                'parcelaMensal' => 50.54,
                'resumo'        => "{$tipo}: Saque de R$ 1814,61 com parcela de R$ 50,54 em 96x",
            ];
        }

        return [
            'tipo'          => $tipo,
            'valorSaque'    => null,
            'parcelaMensal' => $margem,
            'resumo'        => "{$tipo}: Margem disponível de R$ " . number_format($margem, 2, ',', '.') . "/mês (valor do saque calculado na formalização)",
        ];
    }
}
