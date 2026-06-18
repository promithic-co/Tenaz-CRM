<?php

use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

/**
 * @return array{0: ConsultarCreditoInssTool, 1: string}
 */
function breakerTool(): array
{
    $lead = Lead::factory()->create(['tenant_id' => 'tnt-cb', 'cpf' => null, 'credito_json' => null]);

    return [new ConsultarCreditoInssTool($lead), "circuit_breaker_inss_{$lead->tenant_id}"];
}

function callBreaker(object $tool, string $method, mixed ...$args): mixed
{
    $reflection = new ReflectionMethod($tool, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($tool, ...$args);
}

test('starts closed and stays closed below threshold', function () {
    [$tool, $key] = breakerTool();
    config()->set('credflow.circuit_breaker.consultas_falhas_threshold', 5);

    expect(callBreaker($tool, 'circuitState', $key))->toBe('closed');

    callBreaker($tool, 'incrementCircuitBreaker', $key);
    callBreaker($tool, 'incrementCircuitBreaker', $key);

    expect((int) Cache::get($key))->toBe(2)
        ->and(callBreaker($tool, 'circuitState', $key))->toBe('closed');
});

test('opens once failures reach the threshold and sets the cooldown gate', function () {
    [$tool, $key] = breakerTool();
    config()->set('credflow.circuit_breaker.consultas_falhas_threshold', 3);

    for ($i = 0; $i < 3; $i++) {
        callBreaker($tool, 'incrementCircuitBreaker', $key);
    }

    expect((int) Cache::get($key))->toBe(3)
        ->and(Cache::get("{$key}_open"))->not->toBeNull()
        ->and(callBreaker($tool, 'circuitState', $key))->toBe('open');
});

test('atomic increment never resets the window counter under repeated failures', function () {
    [$tool, $key] = breakerTool();
    config()->set('credflow.circuit_breaker.consultas_falhas_threshold', 100);

    for ($i = 0; $i < 6; $i++) {
        callBreaker($tool, 'incrementCircuitBreaker', $key);
    }

    expect((int) Cache::get($key))->toBe(6);
});

test('half-open lets exactly one probe through after the cooldown elapses', function () {
    [$tool, $key] = breakerTool();
    config()->set('credflow.circuit_breaker.consultas_falhas_threshold', 3);

    for ($i = 0; $i < 3; $i++) {
        callBreaker($tool, 'incrementCircuitBreaker', $key);
    }

    // Simulate the cooldown expiring.
    Cache::forget("{$key}_open");

    expect(callBreaker($tool, 'circuitState', $key))->toBe('half_open')
        ->and(callBreaker($tool, 'circuitState', $key))->toBe('open');
});

test('a failed probe re-opens and a success closes the circuit', function () {
    [$tool, $key] = breakerTool();
    config()->set('credflow.circuit_breaker.consultas_falhas_threshold', 3);

    for ($i = 0; $i < 3; $i++) {
        callBreaker($tool, 'incrementCircuitBreaker', $key);
    }
    Cache::forget("{$key}_open");
    expect(callBreaker($tool, 'circuitState', $key))->toBe('half_open');

    // Probe fails → re-open with a fresh cooldown and drop the spent probe claim.
    callBreaker($tool, 'incrementCircuitBreaker', $key);
    expect(Cache::get("{$key}_open"))->not->toBeNull()
        ->and(callBreaker($tool, 'circuitState', $key))->toBe('open');

    // Eventual success closes everything.
    callBreaker($tool, 'closeCircuit', $key);
    expect(Cache::get($key))->toBeNull()
        ->and(Cache::get("{$key}_open"))->toBeNull()
        ->and(callBreaker($tool, 'circuitState', $key))->toBe('closed');
});

test('handle() fast-fails without calling the webhook when the circuit is open', function () {
    Http::fake();
    config()->set('services.credflow.webhook_consulta', 'https://n8n.test/consulta');
    config()->set('credflow.circuit_breaker.consultas_falhas_threshold', 3);

    [$tool, $key] = breakerTool();
    for ($i = 0; $i < 3; $i++) {
        callBreaker($tool, 'incrementCircuitBreaker', $key);
    }

    $result = (string) $tool->handle(new Request(['cpf' => '69747830191']));

    expect($result)->toContain('circuit breaker');
    Http::assertNothingSent();
});
