<?php

use App\Services\WhatsApp\WhatsappTemplateRenderer;

function bodyWithTwoParams(): array
{
    return [
        [
            'type' => 'HEADER',
            'format' => 'IMAGE',
            'example' => ['header_handle' => ['https://example.com/banner.jpg']],
        ],
        [
            'type' => 'BODY',
            'text' => 'Olá {{1}}, sua proposta {{2}} está pronta.',
            'example' => ['body_text' => [['Maria', '#123']]],
        ],
        [
            'type' => 'BUTTONS',
            'buttons' => [
                ['type' => 'URL', 'text' => 'Acessar', 'url' => 'https://x.com/{{1}}', 'example' => ['https://x.com/abc']],
            ],
        ],
    ];
}

it('describes fields for a media header, a two-param body, and a URL button', function () {
    $renderer = new WhatsappTemplateRenderer;

    $description = $renderer->describe(bodyWithTwoParams());

    expect($description['supported'])->toBeTrue()
        ->and($description['unsupported_reason'])->toBeNull();

    $paths = array_map(fn (array $f): string => $f['path'], $description['fields']);
    expect($paths)->toBe(['header.media', 'body.1', 'body.2', 'buttons.0']);
});

it('flags a named (non-numeric) parameter as unsupported', function () {
    $renderer = new WhatsappTemplateRenderer;

    $description = $renderer->describe([
        ['type' => 'BODY', 'text' => 'Olá {{name}}'],
    ]);

    expect($description['supported'])->toBeFalse()
        ->and($description['unsupported_reason'])->toContain('nomeado');
});

it('renders the strict snapshot text with provided parameters', function () {
    $renderer = new WhatsappTemplateRenderer;

    $rendered = $renderer->render(bodyWithTwoParams(), [
        'header' => ['media' => 'https://example.com/real.jpg'],
        'body' => ['1' => 'João', '2' => '#999'],
        'buttons' => ['0' => 'joao'],
    ]);

    expect($rendered['body'])->toBe('Olá João, sua proposta #999 está pronta.')
        ->and($rendered['header'])->toBe(['format' => 'IMAGE', 'media_url' => 'https://example.com/real.jpg'])
        ->and($rendered['text'])->toContain('Olá João, sua proposta #999 está pronta.')
        ->and($rendered['text'])->toContain('[Botão] Acessar');
});

it('render throws when a required body parameter is missing', function () {
    $renderer = new WhatsappTemplateRenderer;

    $renderer->render(bodyWithTwoParams(), [
        'header' => ['media' => 'https://example.com/real.jpg'],
        'body' => ['1' => 'João'],
        'buttons' => ['0' => 'joao'],
    ]);
})->throws(InvalidArgumentException::class, 'Parâmetro obrigatório ausente: body.2.');

it('preview falls back to Meta examples when parameters are absent', function () {
    $renderer = new WhatsappTemplateRenderer;

    $preview = $renderer->preview(bodyWithTwoParams());

    expect($preview['body'])->toBe('Olá Maria, sua proposta #123 está pronta.')
        ->and($preview['header']['media_url'])->toBe('https://example.com/banner.jpg');
});

it('builds a Cloud API payload with body and button parameters', function () {
    $renderer = new WhatsappTemplateRenderer;

    $payload = $renderer->payload(bodyWithTwoParams(), [
        'header' => ['media' => 'https://example.com/real.jpg'],
        'body' => ['1' => 'João', '2' => '#999'],
        'buttons' => ['0' => 'joao'],
    ], '42');

    $body = collect($payload)->firstWhere('type', 'body');
    expect($body['parameters'])->toBe([
        ['type' => 'text', 'text' => 'João'],
        ['type' => 'text', 'text' => '#999'],
    ]);

    $header = collect($payload)->firstWhere('type', 'header');
    expect($header['parameters'][0])->toBe([
        'type' => 'image',
        'image' => ['link' => 'https://example.com/real.jpg'],
    ]);

    $button = collect($payload)->firstWhere('sub_type', 'url');
    expect($button['parameters'][0])->toBe(['type' => 'text', 'text' => 'joao']);
});

it('payload throws when a required parameter is missing', function () {
    $renderer = new WhatsappTemplateRenderer;

    $renderer->payload(bodyWithTwoParams(), [
        'header' => ['media' => 'https://example.com/real.jpg'],
        'body' => ['1' => 'João', '2' => '#999'],
        'buttons' => [],
    ], '42');
})->throws(InvalidArgumentException::class, 'Parâmetro obrigatório ausente: buttons.0.');

it('rejects a non-http media url in the payload', function () {
    $renderer = new WhatsappTemplateRenderer;

    $renderer->payload([
        ['type' => 'HEADER', 'format' => 'IMAGE'],
        ['type' => 'BODY', 'text' => 'Oi'],
    ], [
        'header' => ['media' => 'ftp://example.com/x.jpg'],
    ], '1');
})->throws(InvalidArgumentException::class, 'URL HTTP válida');
