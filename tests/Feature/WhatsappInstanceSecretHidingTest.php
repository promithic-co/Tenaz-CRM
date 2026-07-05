<?php

use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// FE-SEC-01: instance secrets must never reach serialized output (Inertia props / JSON).
test('serialization omits instance secrets', function () {
    $instance = WhatsappInstance::factory()->create([
        'api_key' => 'plaintext-api-key',
        'meta_access_token' => 'super-secret-token',
        'proxy_password' => 'proxy-pass',
        'proxy_username' => 'proxy-user',
        'proxy_host' => '10.0.0.1',
        'proxy_port' => '8080',
    ]);

    $array = $instance->toArray();

    foreach (['api_key', 'meta_access_token', 'proxy_password', 'proxy_username', 'proxy_host', 'proxy_port', 'api_url'] as $secret) {
        expect($array)->not->toHaveKey($secret);
    }

    expect(json_encode($instance))
        ->not->toContain('super-secret-token')
        ->not->toContain('plaintext-api-key')
        ->not->toContain('proxy-pass');
});

// Backend send paths read secrets as properties — $hidden must not affect that.
test('property access still returns decrypted secrets', function () {
    $instance = WhatsappInstance::factory()->create([
        'api_key' => 'plaintext-api-key',
        'meta_access_token' => 'super-secret-token',
    ]);

    expect($instance->api_key)->toBe('plaintext-api-key')
        ->and($instance->meta_access_token)->toBe('super-secret-token');
});

// The leak vector: a nested eager-loaded whatsappInstance relation must also be clean.
test('nested eager-loaded relation omits secrets in serialization', function () {
    $instance = WhatsappInstance::factory()->create([
        'meta_access_token' => 'super-secret-token',
    ]);

    $reloaded = WhatsappInstance::query()->findOrFail($instance->id);

    expect(json_encode(['whatsapp_instance' => $reloaded]))
        ->not->toContain('super-secret-token');
});
