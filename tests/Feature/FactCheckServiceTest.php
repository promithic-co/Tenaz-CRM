<?php

use App\Models\Lead;
use App\Services\FactCheckService;

beforeEach(function () {
    $this->service = app(FactCheckService::class);
});

test('aceita valor de parcela mensal (desconto no benefício) além dos valores liberados', function () {
    $lead = Lead::factory()->create([
        'credito_json' => [
            'status' => 'QUALIFICADO',
            'resumoGeral' => [
                'totais' => [
                    'margemLivre' => 2899.82,
                    'refinanciamento' => 4757.24,
                    'cartoes' => 3629.22,
                    'totalEstimado' => 11286.28,
                ],
            ],
            'beneficios' => [[
                'produtos' => [
                    'emprestimoNovo' => [
                        'valorLiberado' => 2899.82,
                        'parcelaMensal' => 66.0,
                    ],
                    'refinanciamento' => ['contratos' => []],
                    'portabilidade' => ['contratos' => []],
                    'cartoes' => [],
                ],
            ]],
        ],
    ]);

    $response = 'Lucas, para o **Novo**, o valor liberado é de R$ 2.899,82. A parcela mensal estimada é de R$ 66,00. Você gostaria de prosseguir com essa opção?';

    $error = $this->service->validateAgentResponse($lead, $response);

    expect($error)->toBeNull();
});

test('rejeita valor que não existe nos dados do lead', function () {
    $lead = Lead::factory()->create([
        'credito_json' => [
            'status' => 'QUALIFICADO',
            'resumoGeral' => [
                'totais' => [
                    'margemLivre' => 2899.82,
                    'refinanciamento' => 0,
                    'cartoes' => 0,
                    'totalEstimado' => 2899.82,
                ],
            ],
            'beneficios' => [[
                'produtos' => [
                    'emprestimoNovo' => [
                        'valorLiberado' => 2899.82,
                        'parcelaMensal' => 66.0,
                    ],
                    'refinanciamento' => ['contratos' => []],
                    'portabilidade' => ['contratos' => []],
                    'cartoes' => [],
                ],
            ]],
        ],
    ]);

    $response = 'O valor liberado é de R$ 2.899,82 e a parcela é de R$ 999,99.';

    $error = $this->service->validateAgentResponse($lead, $response);

    expect($error)->not->toBeNull();
    expect($error)->toContain('R$ 999,99');
    expect($error)->toContain('não existe');
});

test('aceita prazo 96x quando mencionado para Novo ou Refinanciamento', function () {
    $lead = Lead::factory()->create([
        'credito_json' => [
            'status' => 'QUALIFICADO',
            'resumoGeral' => [
                'totais' => [
                    'margemLivre' => 2899.82,
                    'refinanciamento' => 4757.24,
                    'cartoes' => 0,
                    'totalEstimado' => 7657.06,
                ],
            ],
            'beneficios' => [[
                'produtos' => [
                    'emprestimoNovo' => ['valorLiberado' => 2899.82, 'parcelaMensal' => 66.0],
                    'refinanciamento' => ['contratos' => []],
                    'portabilidade' => ['contratos' => []],
                    'cartoes' => [],
                ],
            ]],
        ],
    ]);

    $response = 'O prazo para Novo e Refinanciamento é de 96 parcelas.';

    expect($this->service->validateAgentResponse($lead, $response))->toBeNull();
});

test('retorna null quando lead não tem totais no credito_json', function () {
    $lead = Lead::factory()->create([
        'credito_json' => [
            'status' => 'QUALIFICADO',
            'resumoGeral' => ['totais' => []],
            'beneficios' => [],
        ],
    ]);

    expect($this->service->validateAgentResponse($lead, 'O valor é R$ 1.000,00'))->toBeNull();
});
