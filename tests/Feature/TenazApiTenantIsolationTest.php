<?php

use App\Models\Lead;
use App\Services\AgentService;

beforeEach(function () {
    config([
        'services.credflow.api_key' => 'legacy-key',
        'services.credflow.default_tenant_id' => 'default',
        'services.credflow.api_keys' => ['tenant-b-key' => 'tenant_b'],
    ]);

    $agent = Mockery::mock(AgentService::class);
    $agent->shouldReceive('process')->andReturn('resposta do agente');
    app()->instance(AgentService::class, $agent);
});

test('rejects missing api key', function () {
    $this->postJson(route('api.tenaz'), [
        'whatsapp' => '5511999998888',
        'message' => 'oi',
    ])->assertStatus(401)->assertJsonFragment(['error' => 'Unauthorized']);

    expect(Lead::count())->toBe(0);
});

test('rejects unknown api key', function () {
    $this->withToken('wrong-key')->postJson(route('api.tenaz'), [
        'whatsapp' => '5511999998888',
        'message' => 'oi',
    ])->assertStatus(401);

    expect(Lead::count())->toBe(0);
});

test('legacy key without tenant_id binds lead to default tenant', function () {
    $this->withToken('legacy-key')->postJson(route('api.tenaz'), [
        'whatsapp' => '5511999998888',
        'message' => 'oi',
    ])->assertOk()->assertJsonFragment(['response' => 'resposta do agente']);

    expect(Lead::where('whatsapp', '5511999998888')->where('tenant_id', 'default')->exists())->toBeTrue();
});

test('rejects tenant_id that does not match the authenticated key', function () {
    $this->withToken('legacy-key')->postJson(route('api.tenaz'), [
        'whatsapp' => '5511999998888',
        'message' => 'oi',
        'tenant_id' => 'tenant_b',
    ])->assertStatus(403)->assertJsonFragment(['error' => 'Forbidden: tenant mismatch']);

    expect(Lead::count())->toBe(0);
});

test('accepts tenant_id that matches the authenticated key', function () {
    $this->withToken('legacy-key')->postJson(route('api.tenaz'), [
        'whatsapp' => '5511999998888',
        'message' => 'oi',
        'tenant_id' => 'default',
    ])->assertOk();

    expect(Lead::where('tenant_id', 'default')->exists())->toBeTrue();
});

test('per-tenant key binds lead to its own tenant', function () {
    $this->withToken('tenant-b-key')->postJson(route('api.tenaz'), [
        'whatsapp' => '5511999997777',
        'message' => 'oi',
    ])->assertOk();

    expect(Lead::where('whatsapp', '5511999997777')->where('tenant_id', 'tenant_b')->exists())->toBeTrue();
});
