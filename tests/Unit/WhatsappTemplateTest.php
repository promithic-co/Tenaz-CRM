<?php

use App\Models\WhatsappTemplate;

it('counts the highest variable index in a template body', function (string $body, int $expected): void {
    expect(WhatsappTemplate::countVariablesIn($body))->toBe($expected);
})->with([
    'no variables' => ['Olá, tudo bem?', 0],
    'single' => ['Olá {{1}}!', 1],
    'sequential' => ['Olá {{1}}, seu protocolo é {{2}}.', 2],
    'returns max not count' => ['Valor {{2}} para {{1}}', 2],
    'duplicate index' => ['{{1}} e de novo {{1}}', 1],
    'empty string' => ['', 0],
]);
