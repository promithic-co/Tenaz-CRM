<?php

use App\Services\AgentService;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;

/**
 * Plan 1.2 (F12): the error classifier must key off exception type and HTTP status code
 * before falling back to message-substring matching, so framework-typed provider errors
 * (rate limit / overload) and status codes are routed correctly instead of misclassified.
 */
function classify(Throwable $e): string
{
    $service = app(AgentService::class);
    $method = (new ReflectionClass($service))->getMethod('classifyError');
    $method->setAccessible(true);

    return $method->invoke($service, $e);
}

function isTransient(Throwable $e): bool
{
    $service = app(AgentService::class);
    $method = (new ReflectionClass($service))->getMethod('isTransientError');
    $method->setAccessible(true);

    return $method->invoke($service, $e);
}

test('typed RateLimitedException classifies as transient rate_limit', function () {
    $e = RateLimitedException::forProvider('openai', 429);

    expect(classify($e))->toBe('rate_limit')
        ->and(isTransient($e))->toBeTrue();
});

test('typed ProviderOverloadedException classifies as transient server_error', function () {
    $e = new ProviderOverloadedException('AI provider [openai] is overloaded.', 503);

    expect(classify($e))->toBe('server_error')
        ->and(isTransient($e))->toBeTrue();
});

test('HTTP status code drives classification when no typed exception is present', function () {
    expect(classify(new Exception('opaque provider failure', 429)))->toBe('rate_limit')
        ->and(classify(new Exception('opaque provider failure', 503)))->toBe('server_error');
});

test('config/programming errors are non-transient unknown', function () {
    $e = new RuntimeException('Unsupported provider [bogus].');

    expect(classify($e))->toBe('unknown')
        ->and(isTransient($e))->toBeFalse();
});

test('message substring fallback still classifies opaque wrapped errors', function () {
    expect(classify(new Exception('Request timeout after 30s')))->toBe('timeout')
        ->and(classify(new Exception('connection refused')))->toBe('connection_error')
        ->and(classify(new Exception('context length exceeded')))->toBe('context_overflow');

    expect(isTransient(new Exception('context length exceeded')))->toBeFalse();
});
