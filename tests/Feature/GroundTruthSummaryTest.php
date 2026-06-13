<?php

use App\Ai\Tools\ConsultarCreditoInssTool;

it('summarizes credito_json with a free margin (Crédito Novo path) plus refin/portab and a cartão', function () {
    $creditoJson = [
        'resumoGeral' => [
            'totais' => [
                'margemLivre' => 500.0,
                'refinanciamento' => 12000.0,
                'portabilidade' => 350.0,
            ],
        ],
        'beneficios' => [
            [
                'produtos' => [
                    'emprestimoNovo' => ['valorLiberado' => 8000.0, 'parcelaMensal' => 250.0],
                    'refinanciamento' => ['totalLiberado' => 9000.0],
                    'portabilidade' => ['totalParcelas' => 300.0],
                    'cartoes' => [
                        ['tipo' => 'RMC', 'valorSaque' => 1500.0, 'parcelaMensal' => 90.0],
                    ],
                ],
            ],
        ],
    ];

    expect(ConsultarCreditoInssTool::buildGroundTruthSummary($creditoJson))->toBe([
        'products' => [
            [
                'name' => 'Crédito Novo',
                'valor_total' => 8000.0,
                'valor_parcela' => 250.0,
                'note' => 'valor_total = valor liberado em mãos; valor_parcela = desconto mensal no benefício',
            ],
            [
                'name' => 'Refinanciamento',
                'valor_total' => 9000.0,
                'valor_parcela' => null,
                'note' => 'Troco (não gera parcela nova). XOR com Portabilidade — mesmo contrato, um ou outro.',
            ],
            [
                'name' => 'Portabilidade',
                'valor_total' => null,
                'valor_parcela' => 300.0,
                'note' => 'Redução de parcela (não libera troco). XOR com Refinanciamento.',
            ],
            [
                'name' => 'RMC',
                'valor_total' => 1500.0,
                'valor_parcela' => 90.0,
                'note' => 'valor_total = saque; valor_parcela = desconto mensal',
            ],
        ],
        'refin_vs_portab_note' => 'Refinanciamento e Portabilidade usam os mesmos contratos: o cliente escolhe UM ou OUTRO (XOR). Refin = mesmo banco, recebe troco; Portab = troca de banco, reduz parcela.',
    ]);
});

it('omits Crédito Novo and falls back to totais when margemLivre is zero and cartões are absent', function () {
    $creditoJson = [
        'resumoGeral' => [
            'totais' => [
                'margemLivre' => 0,
                'refinanciamento' => 4000.0,
                'portabilidade' => 0,
            ],
        ],
        'beneficios' => [
            [
                'produtos' => [],
            ],
        ],
    ];

    expect(ConsultarCreditoInssTool::buildGroundTruthSummary($creditoJson))->toBe([
        'products' => [
            [
                'name' => 'Refinanciamento',
                'valor_total' => 4000.0,
                'valor_parcela' => null,
                'note' => 'Troco (não gera parcela nova). XOR com Portabilidade — mesmo contrato, um ou outro.',
            ],
            [
                'name' => 'Portabilidade',
                'valor_total' => null,
                'valor_parcela' => null,
                'note' => 'Redução de parcela (não libera troco). XOR com Refinanciamento.',
            ],
        ],
        'refin_vs_portab_note' => 'Refinanciamento e Portabilidade usam os mesmos contratos: o cliente escolhe UM ou OUTRO (XOR). Refin = mesmo banco, recebe troco; Portab = troca de banco, reduz parcela.',
    ]);
});

it('coerces missing values to nulls and defaults a cartão with no saque/parcela', function () {
    $creditoJson = [
        'beneficios' => [
            [
                'produtos' => [
                    'cartoes' => [
                        ['parcelaMensal' => 0],
                        ['tipo' => 'RCC', 'valorSaque' => 0],
                    ],
                ],
            ],
        ],
    ];

    expect(ConsultarCreditoInssTool::buildGroundTruthSummary($creditoJson))->toBe([
        'products' => [
            [
                'name' => 'Refinanciamento',
                'valor_total' => null,
                'valor_parcela' => null,
                'note' => 'Troco (não gera parcela nova). XOR com Portabilidade — mesmo contrato, um ou outro.',
            ],
            [
                'name' => 'Portabilidade',
                'valor_total' => null,
                'valor_parcela' => null,
                'note' => 'Redução de parcela (não libera troco). XOR com Refinanciamento.',
            ],
            [
                'name' => 'Cartão',
                'valor_total' => null,
                'valor_parcela' => null,
                'note' => 'valor_total = saque; valor_parcela = desconto mensal',
            ],
            [
                'name' => 'RCC',
                'valor_total' => 0.0,
                'valor_parcela' => null,
                'note' => 'valor_total = saque; valor_parcela = desconto mensal',
            ],
        ],
        'refin_vs_portab_note' => 'Refinanciamento e Portabilidade usam os mesmos contratos: o cliente escolhe UM ou OUTRO (XOR). Refin = mesmo banco, recebe troco; Portab = troca de banco, reduz parcela.',
    ]);
});
