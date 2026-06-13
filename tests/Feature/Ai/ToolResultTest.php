<?php

use App\Ai\Support\ToolResult;

test('success result serializes status and message to JSON', function () {
    $result = ToolResult::success('Status atualizado para qualificado.');

    $decoded = json_decode((string) $result, true);

    expect($decoded['status'])->toBe('success');
    expect($decoded['message'])->toBe('Status atualizado para qualificado.');
    expect($decoded)->not->toHaveKey('hint');
    expect($decoded)->not->toHaveKey('data');
});

test('success result includes hint when provided', function () {
    $result = ToolResult::success('Consulta concluída.', 'Prossiga com a apresentação da oferta.');

    $decoded = json_decode((string) $result, true);

    expect($decoded['status'])->toBe('success');
    expect($decoded['hint'])->toBe('Prossiga com a apresentação da oferta.');
});

test('error result includes hint for agent recovery', function () {
    $result = ToolResult::error(
        'Consulta INSS falhou.',
        'Tente novamente uma vez. Se falhar, acione escalar_para_humano.'
    );

    $decoded = json_decode((string) $result, true);

    expect($decoded['status'])->toBe('error');
    expect($decoded['message'])->toBe('Consulta INSS falhou.');
    expect($decoded['hint'])->toContain('escalar_para_humano');
});

test('already_done result omits hint', function () {
    $result = ToolResult::alreadyDone('Lead já foi escalado anteriormente.');

    $decoded = json_decode((string) $result, true);

    expect($decoded['status'])->toBe('already_done');
    expect($decoded)->not->toHaveKey('hint');
});

test('blocked result serializes correctly', function () {
    $result = ToolResult::blocked("Transição 'novo' → 'convertido' não permitida.", 'Ajuste a estratégia.');

    $decoded = json_decode((string) $result, true);

    expect($decoded['status'])->toBe('blocked');
    expect($decoded['hint'])->toBe('Ajuste a estratégia.');
});

test('isSuccess returns true only for success status', function () {
    expect(ToolResult::success('ok')->isSuccess())->toBeTrue();
    expect(ToolResult::error('fail')->isSuccess())->toBeFalse();
    expect(ToolResult::alreadyDone('done')->isSuccess())->toBeFalse();
    expect(ToolResult::blocked('blocked')->isSuccess())->toBeFalse();
});

test('isError returns true only for error status', function () {
    expect(ToolResult::error('fail')->isError())->toBeTrue();
    expect(ToolResult::success('ok')->isError())->toBeFalse();
});

test('result with data includes data field in JSON', function () {
    $result = ToolResult::success('Dados recuperados.', null, ['key' => 'value', 'count' => 3]);

    $decoded = json_decode((string) $result, true);

    expect($decoded['data'])->toBe(['key' => 'value', 'count' => 3]);
});

test('JSON output preserves unicode characters without escaping', function () {
    $result = ToolResult::success('Crédito disponível: R$ 5.200,00');

    $json = (string) $result;

    expect($json)->toContain('Crédito disponível');
    expect($json)->not->toContain('\u00'); // no unicode escape sequences
});

test('ToolResult implements Stringable interface', function () {
    $result = ToolResult::success('test');

    expect($result)->toBeInstanceOf(Stringable::class);
    expect((string) $result)->toBeString()->toBeJson();
});
