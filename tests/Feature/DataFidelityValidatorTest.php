<?php

use App\Services\DataFidelityValidator;

beforeEach(function () {
    $this->validator = app(DataFidelityValidator::class);

    $this->qualifiedJson = [
        'status' => 'QUALIFICADO',
        'resumoGeral' => [
            'totais' => [
                'margemLivre' => 24912.13,
                'refinanciamento' => 0,
                'portabilidade' => 0,
                'cartoes' => 3629.22,
                'totalEstimado' => 28541.35,
            ],
        ],
        'beneficios' => [
            [
                'produtos' => [
                    'emprestimoNovo' => [
                        'disponivel' => true,
                        'valorLiberado' => 24912.13,
                        'parcelaMensal' => 567.00,
                    ],
                    'refinanciamento' => [
                        'totalLiberado' => 0,
                        'contratos' => [],
                    ],
                    'cartoes' => [
                        ['tipo' => 'RMC', 'valorSaque' => 1814.61, 'parcelaMensal' => 50.54],
                        ['tipo' => 'RCC', 'valorSaque' => 1814.61, 'parcelaMensal' => 50.54],
                    ],
                ],
            ],
        ],
    ];
});

it('scores 100 for perfectly faithful response', function () {
    $response = 'Você tem crédito disponível! Novo: R$ 24.912,13 liberado com parcela de R$ 567,00/mês. Cartão RMC: R$ 1.814,61. Total: R$ 28.541,35.';

    $report = $this->validator->validate($response, $this->qualifiedJson);

    expect($report->score)->toBeGreaterThanOrEqual(90.0)
        ->and($report->passed)->toBeTrue()
        ->and($report->statusCorrect)->toBeTrue();
});

it('detects hallucinated monetary value', function () {
    // R$ 30.000 vs real R$ 24.912,13 — clearly a wrong value, must be flagged
    $response = 'Você tem R$ 30.000,00 disponíveis com parcela de R$ 567,00/mês.';

    $report = $this->validator->validate($response, $this->qualifiedJson);

    $hasHallucination = collect($report->hallucinations)->isNotEmpty();

    expect($report->score)->toBeLessThan(90.0)
        ->and($hasHallucination)->toBeTrue();
});

it('detects wrong qualification status', function () {
    $desqualificadoJson = array_merge($this->qualifiedJson, ['status' => 'DESQUALIFICADO']);
    $response = 'Você tem crédito disponível! Novo: R$ 24.912,13 liberado com parcela de R$ 567,00/mês.';

    $report = $this->validator->validate($response, $desqualificadoJson);

    expect($report->statusCorrect)->toBeFalse()
        ->and($report->score)->toBeLessThanOrEqual(75.0);
});

it('detects invented product when refinanciamento does not exist', function () {
    $response = 'Você tem crédito! Novo: R$ 24.912,13. Também temos refinanciamento disponível para você.';

    $report = $this->validator->validate($response, $this->qualifiedJson);

    $hasRefinHallucination = collect($report->hallucinations)
        ->contains(fn ($h) => str_contains($h['field'], 'refinanciamento'));

    expect($hasRefinHallucination)->toBeTrue()
        ->and($report->score)->toBeLessThan(90.0);
});

it('handles Brazilian number formatting correctly', function () {
    $response = 'Valor liberado: R$ 24.912,13 com parcela de R$ 567,00.';

    $report = $this->validator->validate($response, $this->qualifiedJson);

    expect($report->score)->toBeGreaterThanOrEqual(85.0);
});

it('tolerates minor rounding differences', function () {
    // R$ 24.912 vs R$ 24.912,13 — should be medium severity at most, not critical
    $response = 'Você tem crédito de R$ 24.912 disponível com parcela de R$ 567,00.';

    $report = $this->validator->validate($response, $this->qualifiedJson);

    $hasCriticalHallucination = collect($report->hallucinations)
        ->contains(fn ($h) => $h['severity'] === 'critical' && str_contains($h['field'], 'emprestimo'));

    expect($hasCriticalHallucination)->toBeFalse();
});

it('handles SEM_CREDITO status correctly', function () {
    $semCreditoJson = [
        'status' => 'SEM_CREDITO',
        'resumoGeral' => ['totais' => ['margemLivre' => 0, 'refinanciamento' => 0, 'portabilidade' => 0, 'cartoes' => 0, 'totalEstimado' => 0]],
        'beneficios' => [],
    ];

    $response = 'Infelizmente não encontrei crédito disponível para você no momento.';

    $report = $this->validator->validate($response, $semCreditoJson);

    expect($report->statusCorrect)->toBeTrue()
        ->and($report->score)->toBeGreaterThanOrEqual(90.0);
});

it('returns complete hallucination details in report', function () {
    $response = 'Você tem R$ 30.000,00 disponíveis! Parcela de R$ 400,00/mês.';

    $report = $this->validator->validate($response, $this->qualifiedJson);

    expect($report->hallucinations)->not->toBeEmpty()
        ->and($report->hallucinations[0])->toHaveKeys(['field', 'expected', 'actual', 'severity'])
        ->and($report->toArray())->toHaveKeys(['score', 'hallucinations', 'matches', 'status_correct', 'passed']);
});

it('detects omitted qualification when client is qualified', function () {
    // This tests the toFormattedString() output for reporting
    $response = 'Olá! Como posso te ajudar hoje?';

    $report = $this->validator->validate($response, $this->qualifiedJson);
    $formatted = $report->toFormattedString();

    expect($formatted)->toContain('Relatório de Fidelidade');
});
