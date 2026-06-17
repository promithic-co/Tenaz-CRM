<?php

use App\Models\Lead;
use App\Services\AgentService;

beforeEach(function (): void {
    config([
        'services.credflow.api_key' => 'test-secret',
        'services.credflow.api_keys' => [
            'key-tenaz' => 'tenant-tenaz',
            'key-legacy' => 'tenant-legacy',
            'key-optout' => 'tenant-optout',
        ],
    ]);
});

test('tenaz direct agent endpoint accepts the existing integration payload', function (): void {
    $agentService = Mockery::mock(AgentService::class);
    $agentService
        ->shouldReceive('process')
        ->once()
        ->andReturn('Resposta Tenaz');

    $this->app->instance(AgentService::class, $agentService);

    $this->postJson(route('api.tenaz'), [
        'whatsapp' => '5511999999999',
        'message' => 'Oi',
        'tenant_id' => 'tenant-tenaz',
        'modo' => 'receptivo',
    ], [
        'Authorization' => 'Bearer key-tenaz',
    ])
        ->assertSuccessful()
        ->assertJson(['response' => 'Resposta Tenaz']);

    expect(Lead::query()
        ->where('tenant_id', 'tenant-tenaz')
        ->where('whatsapp', '5511999999999')
        ->exists())->toBeTrue();
});

test('legacy aria direct agent endpoint remains available', function (): void {
    $agentService = Mockery::mock(AgentService::class);
    $agentService
        ->shouldReceive('process')
        ->once()
        ->andReturn('Resposta legada');

    $this->app->instance(AgentService::class, $agentService);

    $this->postJson(route('api.aria'), [
        'whatsapp' => '5511888888888',
        'message' => 'Oi',
        'tenant_id' => 'tenant-legacy',
        'modo' => 'receptivo',
    ], [
        'Authorization' => 'Bearer key-legacy',
    ])
        ->assertSuccessful()
        ->assertHeader('Deprecation', 'true')
        ->assertJson(['response' => 'Resposta legada']);

    expect(Lead::query()
        ->where('tenant_id', 'tenant-legacy')
        ->where('whatsapp', '5511888888888')
        ->exists())->toBeTrue();
});

test('tenaz direct agent endpoint returns the opt-out response shape', function (): void {
    $agentService = Mockery::mock(AgentService::class);
    $agentService
        ->shouldReceive('process')
        ->once()
        ->andReturn(null);

    $this->app->instance(AgentService::class, $agentService);

    $this->postJson(route('api.tenaz'), [
        'whatsapp' => '5511777777777',
        'message' => 'Sair',
        'tenant_id' => 'tenant-optout',
        'modo' => 'receptivo',
    ], [
        'Authorization' => 'Bearer key-optout',
    ])
        ->assertSuccessful()
        ->assertExactJson(['response' => null]);
});

test('tenaz direct agent endpoint rejects a missing bearer token', function (): void {
    $this->postJson(route('api.tenaz'), [
        'whatsapp' => '5511999999999',
        'message' => 'Oi',
    ])->assertUnauthorized();
});

test('tenaz direct agent endpoint rejects an invalid bearer token', function (): void {
    $this->postJson(route('api.tenaz'), [
        'whatsapp' => '5511999999999',
        'message' => 'Oi',
    ], [
        'Authorization' => 'Bearer wrong-secret',
    ])->assertUnauthorized();
});

test('tenaz direct agent endpoint validates the request payload', function (): void {
    $this->postJson(route('api.tenaz'), [
        'whatsapp' => 'not-a-number',
        'message' => '',
        'modo' => 'invalid-mode',
    ], [
        'Authorization' => 'Bearer test-secret',
    ])->assertJsonValidationErrors(['whatsapp', 'message', 'modo']);
});
