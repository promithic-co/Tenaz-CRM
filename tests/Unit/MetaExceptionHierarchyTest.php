<?php

use App\Exceptions\MetaApiException;
use App\Exceptions\MetaInvalidNumberException;
use App\Exceptions\MetaNoWhatsAppException;
use App\Exceptions\MetaRateLimitException;

it('all specific exceptions extend base MetaApiException', function (): void {
    expect(new MetaRateLimitException)->toBeInstanceOf(MetaApiException::class)
        ->and(new MetaInvalidNumberException)->toBeInstanceOf(MetaApiException::class)
        ->and(new MetaNoWhatsAppException)->toBeInstanceOf(MetaApiException::class);
});

it('base MetaApiException extends RuntimeException', function (): void {
    expect(new MetaApiException)->toBeInstanceOf(RuntimeException::class);
});

it('code property is preserved on construction', function (): void {
    $e = new MetaRateLimitException('Rate limit exceeded', 130429);
    expect($e->getCode())->toBe(130429);
});
