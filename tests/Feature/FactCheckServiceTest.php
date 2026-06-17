<?php

use App\Models\Lead;
use App\Services\FactCheckService;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->service = app(FactCheckService::class);
});

/** Lead whose only released value is R$ 2.899,82, for the conservative bare-number tests. */
function leadWithSingleValue(): Lead
{
    return Lead::factory()->create([
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
                    'emprestimoNovo' => ['valorLiberado' => 2899.82, 'parcelaMensal' => 66.0],
                    'refinanciamento' => ['contratos' => []],
                    'portabilidade' => ['contratos' => []],
                    'cartoes' => [],
                ],
            ]],
        ],
    ]);
}

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

test('rejeita número cru >= 1000 sem marcador monetário que não existe nos dados', function () {
    $lead = leadWithSingleValue();

    $error = $this->service->validateAgentResponse($lead, 'Você tem 1500 livres para usar agora');

    expect($error)->not->toBeNull()
        ->and($error)->toContain('R$ 1.500,00')
        ->and($error)->toContain('não existe');
});

test('ignora valores que parecem ano (1900-2100) na passada conservadora', function () {
    $lead = leadWithSingleValue();

    expect($this->service->validateAgentResponse($lead, 'Estamos em 2026 e seu contrato começou em 1998'))->toBeNull();
});

test('aceita número cru que confere com um valor liberado', function () {
    $lead = leadWithSingleValue();

    expect($this->service->validateAgentResponse($lead, 'Você tem 2899 disponíveis aproximadamente'))->toBeNull();
});

test('ignora números crus abaixo de 1000 sem marcador monetário (conservador)', function () {
    $lead = leadWithSingleValue();

    expect($this->service->validateAgentResponse($lead, 'Tem mais ou menos 500 de diferença aqui'))->toBeNull();
});

test('não registra a resposta crua nem os valores brutos no log de falha', function () {
    $lead = leadWithSingleValue();

    Log::spy();

    $this->service->validateAgentResponse($lead, 'O valor liberado é de R$ 999,99 apenas');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $event, array $context): bool {
            return $event === 'aria.fact_check_failed'
                && ! array_key_exists('resposta_crua', $context)
                && ! array_key_exists('totais_validos', $context)
                && array_key_exists('valores_validos_count', $context)
                && array_key_exists('response_len', $context);
        });
});
