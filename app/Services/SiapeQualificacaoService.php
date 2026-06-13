<?php

namespace App\Services;

use App\Models\AgentOperationalRule;

/**
 * Qualifica resposta da API Promosys consultaOfflineSiape.
 * Aplica regras de margem, contratos e elegibilidade para servidores SIAPE (convênio).
 *
 * Produtos: Empréstimo Novo, Refinanciamento, Portabilidade, Cartão RMC/RCC.
 */
class SiapeQualificacaoService
{
    private ?AgentOperationalRule $rules = null;

    public function __construct(?AgentOperationalRule $rules = null)
    {
        $this->rules = $rules;
    }

    private function r(string $key): mixed
    {
        if ($this->rules) {
            return $this->rules->regra($key);
        }

        return AgentOperationalRule::$REGRAS_GLOBAIS_PADRAO[$key] ?? null;
    }

    private function bancoAtivo(string $nomeBanco, string $produto): bool
    {
        if (! $this->rules) {
            return true;
        }

        $siglasBancos = $this->rules->bancosAtivosParaProduto($produto);

        if (empty($siglasBancos)) {
            return false;
        }

        $nomeUpper = strtoupper($nomeBanco);

        foreach ($siglasBancos as $sigla) {
            if (str_contains($nomeUpper, $sigla)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $consulta  Single entry from Consulta array
     * @return array<string, mixed> Standardized qualification result
     */
    public function qualificar(array $consulta): array
    {
        $nomeCompleto = $consulta['NOME'] ?? 'Cliente';
        $primeiroNome = explode(' ', $nomeCompleto)[0];
        $cpf = $consulta['FULL_CPF'] ?? '';
        $idade = (int) ($consulta['IDADE'] ?? 0);

        $matricula = $consulta['MATRICULA'] ?? [];
        $contratos = $consulta['CONTRATO'] ?? [];

        $codigoMatricula = $matricula['Codigo'] ?? '';
        $orgaoNome = $matricula['Orgao']['Nome'] ?? '';
        $orgaoCodigo = $matricula['Orgao']['Codigo'] ?? '';
        $situacaoFunc = $matricula['SituacaoFunc'] ?? '';
        $regimeJuridico = $matricula['RJUR'] ?? '';
        $rendimentoBruto = (float) ($matricula['RendimentoBruto'] ?? 0);
        $rendimentoLiquido = (float) ($matricula['RendimentoLiquido'] ?? 0);
        $margemEmprestimo = (float) ($matricula['MargemEmprestimo'] ?? 0);
        $margemCartaoRmc = (float) ($matricula['MargemCartaoRmc'] ?? 0);
        $margemCartaoRcc = (float) ($matricula['MargemCartaoRcc'] ?? 0);

        // Qualification checks
        $motivosDesqualificacao = [];
        $idadeMax = (int) ($this->r('idade_maxima') ?? 80);

        $situacoesInativas = ['APOSENTADO POR INVALIDEZ', 'EXONERADO', 'FALECIDO', 'DEMITIDO'];
        $situacaoUpper = strtoupper($situacaoFunc);
        foreach ($situacoesInativas as $inativa) {
            if (str_contains($situacaoUpper, $inativa)) {
                $motivosDesqualificacao[] = "Situação funcional incompatível: {$situacaoFunc}";
                break;
            }
        }

        if ($idade > $idadeMax) {
            $motivosDesqualificacao[] = "Idade superior a {$idadeMax} anos ({$idade})";
        }

        if (empty($orgaoCodigo)) {
            $motivosDesqualificacao[] = 'Órgão não identificado';
        }

        $isDesqualificado = count($motivosDesqualificacao) > 0;

        // Empréstimo Novo
        $coeficientePadrao = 0.025;
        $prazoMaximo = 96;
        $valorLiberadoNovo = $margemEmprestimo > 0
            ? round($margemEmprestimo / $coeficientePadrao, 2)
            : 0;
        $minNovo = (float) ($this->r('valor_minimo_liberado_novo') ?? 500);

        $emprestimoNovo = [
            'disponivel' => ! $isDesqualificado && $margemEmprestimo > 0 && $valorLiberadoNovo > $minNovo,
            'parcelaMensal' => $margemEmprestimo,
            'valorLiberado' => $valorLiberadoNovo,
            'resumo' => (! $isDesqualificado && $valorLiberadoNovo > $minNovo)
                ? 'Margem de R$ '.number_format($margemEmprestimo, 2, '.', '').' liberando aprox. R$ '.number_format($valorLiberadoNovo, 2, '.', '')
                : ($margemEmprestimo > 0
                    ? 'Valor de R$ '.number_format($valorLiberadoNovo, 2, '.', '').' abaixo do mínimo (R$ '.number_format($minNovo, 2, '.', '').')'
                    : 'Sem margem de empréstimo disponível'),
        ];

        // Refinanciamento
        $contratosRefin = [];
        $totalRefinanciamento = 0.0;
        $minRefin = (float) ($this->r('valor_minimo_liberado_refin') ?? 500);

        foreach ($contratos as $contrato) {
            $saldoDevedor = (float) ($contrato['Saldo_Devedor'] ?? 0);
            $vlParcela = (float) ($contrato['Vl_Parcela'] ?? 0);
            $parcRestantes = (int) ($contrato['ParcelasRestantes'] ?? 0);
            $prazoTotal = (int) ($contrato['PrazoTotal'] ?? 0);
            $nomeBanco = $contrato['Banco'] ?? '';
            $coefContrato = (float) ($contrato['Coeficiente'] ?? $coeficientePadrao);

            // Estimate refinancing value: new loan at full term minus current balance
            $novoValorTotal = $prazoTotal > 0 && $coefContrato > 0
                ? round($vlParcela / $coefContrato, 2)
                : 0;
            $valorLiberado = max(0, round($novoValorTotal - $saldoDevedor, 2));

            if ($valorLiberado > $minRefin && $this->bancoAtivo($nomeBanco, 'refin')) {
                $totalRefinanciamento += $valorLiberado;
                $contratosRefin[] = [
                    'banco' => $nomeBanco,
                    'contrato' => $contrato['Contrato'] ?? '',
                    'valorParcela' => $vlParcela,
                    'saldoDevedor' => $saldoDevedor,
                    'parcelasRestantes' => $parcRestantes,
                    'valorLiberado' => $valorLiberado,
                    'resumo' => "{$nomeBanco}: Parcela R$ ".number_format($vlParcela, 2, '.', '').' (Saldo: R$ '.number_format($saldoDevedor, 2, '.', '').') libera aprox. R$ '.number_format($valorLiberado, 2, '.', ''),
                ];
            }
        }

        $refinanciamento = [
            'contratos' => $contratosRefin,
            'totalLiberado' => round($totalRefinanciamento, 2),
        ];

        // Portabilidade
        $contratosPort = [];
        $totalParcelasPort = 0.0;
        $minParcelaPort = (float) ($this->r('valor_minimo_parcela_portabilidade') ?? 100);
        $minPercPago = (float) ($this->r('percentual_minimo_pago_portabilidade') ?? 0.20);

        foreach ($contratos as $contrato) {
            $vlParcela = (float) ($contrato['Vl_Parcela'] ?? 0);
            $parcRestantes = (int) ($contrato['ParcelasRestantes'] ?? 0);
            $prazoTotal = (int) ($contrato['PrazoTotal'] ?? 0);
            $parcPagas = $prazoTotal - $parcRestantes;
            $percPago = $prazoTotal > 0 ? $parcPagas / $prazoTotal : 0;
            $nomeBanco = $contrato['Banco'] ?? '';

            if (! $isDesqualificado && $vlParcela > $minParcelaPort && $percPago >= $minPercPago && $this->bancoAtivo($nomeBanco, 'port')) {
                $totalParcelasPort += $vlParcela;
                $contratosPort[] = [
                    'banco' => $nomeBanco,
                    'valorParcela' => $vlParcela,
                    'parcelasPagas' => $parcPagas,
                    'prazoTotal' => $prazoTotal,
                    'percentualPago' => (int) round($percPago * 100),
                    'resumo' => "{$nomeBanco}: Parcela R$ ".number_format($vlParcela, 2, '.', '').' ('.(int) round($percPago * 100).'% pago) — elegível para portabilidade',
                ];
            }
        }

        $portabilidade = [
            'contratos' => $contratosPort,
            'totalParcelas' => round($totalParcelasPort, 2),
        ];

        // Cartões RMC / RCC
        $cartoes = [];

        if (! $isDesqualificado && $margemCartaoRmc > 0) {
            $cartoes[] = [
                'tipo' => 'Cartão RMC',
                'margemMensal' => $margemCartaoRmc,
                'valorSaque' => null,
                'parcelaMensal' => $margemCartaoRmc,
                'resumo' => 'Cartão RMC: Margem disponível de R$ '.number_format($margemCartaoRmc, 2, ',', '.').'/mês',
            ];
        }
        if (! $isDesqualificado && $margemCartaoRcc > 0) {
            $cartoes[] = [
                'tipo' => 'Cartão RCC',
                'margemMensal' => $margemCartaoRcc,
                'valorSaque' => null,
                'parcelaMensal' => $margemCartaoRcc,
                'resumo' => 'Cartão RCC: Margem disponível de R$ '.number_format($margemCartaoRcc, 2, ',', '.').'/mês',
            ];
        }

        $totalCartoes = array_reduce($cartoes, fn ($sum, $c) => $sum + ($c['margemMensal'] ?? 0), 0.0);

        // Totals — XOR: refin e port não se somam (mesmos contratos)
        $qualMargemLivre = $emprestimoNovo['disponivel'] ? round($valorLiberadoNovo, 2) : 0;
        $qualRefinanciamento = round($totalRefinanciamento, 2);
        $qualPortabilidade = round($totalParcelasPort, 2);
        $totalEstimado = round($qualMargemLivre + $qualRefinanciamento + $totalCartoes, 2);

        // Status
        if ($isDesqualificado) {
            $status = 'DESQUALIFICADO';
        } elseif ($qualMargemLivre > 0 || $qualRefinanciamento > 0 || $qualPortabilidade > 0 || $totalCartoes > 0) {
            $status = 'QUALIFICADO';
        } else {
            $status = 'SEM_CREDITO';
        }

        // Summary text
        $rendaStr = 'R$ '.number_format($rendimentoLiquido, 2, ',', '.');

        if ($status === 'QUALIFICADO') {
            $produtos = [];
            if ($qualMargemLivre > 0) {
                $produtos[] = 'Novo';
            }
            if ($qualRefinanciamento > 0) {
                $produtos[] = 'Refin';
            }
            if ($qualPortabilidade > 0) {
                $produtos[] = 'Port';
            }
            if ($totalCartoes > 0) {
                $produtos[] = 'Cartão';
            }

            $textoResumo = "{$nomeCompleto} ({$idade} anos, Renda Líq: {$rendaStr}). "
                .'QUALIFICADO ['.implode(', ', $produtos).']. '
                .'Total estimado: R$ '.number_format($totalEstimado, 2, ',', '.').'.';
        } elseif ($status === 'SEM_CREDITO') {
            $textoResumo = "{$nomeCompleto} ({$idade} anos, Renda Líq: {$rendaStr}). "
                .'SEM_CREDITO — Servidor ativo mas nenhum produto atingiu limiar mínimo.';
        } else {
            $textoResumo = "{$nomeCompleto} ({$idade} anos, Renda Líq: {$rendaStr}). "
                .'DESQUALIFICADO — '.implode('; ', $motivosDesqualificacao).'.';
        }

        return [
            'niche' => 'siape',
            'status' => $status,
            'cliente' => [
                'nome' => $nomeCompleto,
                'primeiroNome' => $primeiroNome,
                'cpf' => $cpf,
                'idade' => $idade,
            ],
            'matricula' => [
                'codigo' => $codigoMatricula,
                'orgao' => $orgaoNome,
                'orgaoCodigo' => $orgaoCodigo,
                'situacaoFuncional' => $situacaoFunc,
                'regimeJuridico' => $regimeJuridico,
                'rendimentoBruto' => $rendimentoBruto,
                'rendimentoLiquido' => $rendimentoLiquido,
            ],
            'resumoGeral' => [
                'qualificado' => $status === 'QUALIFICADO',
                'totais' => [
                    'margemLivre' => $qualMargemLivre,
                    'refinanciamento' => $qualRefinanciamento,
                    'portabilidade' => $qualPortabilidade,
                    'cartoes' => round($totalCartoes, 2),
                    'totalEstimado' => $totalEstimado,
                ],
                'textoResumo' => $textoResumo,
            ],
            'produtos' => [
                'emprestimoNovo' => $emprestimoNovo,
                'refinanciamento' => $refinanciamento,
                'portabilidade' => $portabilidade,
                'cartoes' => $cartoes,
            ],
        ];
    }
}
