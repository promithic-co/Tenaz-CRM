<?php

use App\Services\AutoTagEvidenceSanitizer;

describe('AutoTagEvidenceSanitizer', function () {
    test('redacts CPF format', function () {
        $sanitizer = new AutoTagEvidenceSanitizer;

        $result = $sanitizer->sanitize('Cliente CPF 123.456.789-01 mostrou interesse');

        expect($result)->not->toContain('123.456.789-01');
        expect($result)->toContain('[cpf]');
    });

    test('redacts phone-like digit runs', function () {
        $sanitizer = new AutoTagEvidenceSanitizer;

        $result = $sanitizer->sanitize('Ligue para 11 98765-4321 amanhã');

        expect($result)->not->toContain('98765-4321');
        expect($result)->toContain('[numero]');
    });

    test('redacts emails', function () {
        $sanitizer = new AutoTagEvidenceSanitizer;

        $result = $sanitizer->sanitize('Contato: joao.silva@exemplo.com confirmou interesse');

        expect($result)->not->toContain('joao.silva@exemplo.com');
        expect($result)->toContain('[email]');
    });

    test('squishes whitespace', function () {
        $sanitizer = new AutoTagEvidenceSanitizer;

        $result = $sanitizer->sanitize("Cliente   mostrou\n\ninteresse   forte");

        expect($result)->toBe('Cliente mostrou interesse forte');
    });

    test('caps to 180 chars', function () {
        $sanitizer = new AutoTagEvidenceSanitizer;

        $longText = str_repeat('a', 200);
        $result = $sanitizer->sanitize($longText);

        expect(mb_strlen($result))->toBe(180);
    });
});
