<?php

use App\Jobs\SendInboundLeadWhatsAppJob;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['services.ura.api_key' => 'test-ura-key-12345']);
});

/**
 * @return array<string, mixed>
 */
function makeInboundPayload(array $overrides = []): array
{
    $user = userWithTenant();
    $tenant = $user->tenants()->first();
    $tenantId = (string) $tenant->id;

    $whatsappInstance = WhatsappInstance::factory()->create(['tenant_id' => $tenantId]);
    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
    ]);

    return array_merge([
        'phone' => '+5511999998888',
        'voice_instance_id' => $voiceInstance->id,
    ], $overrides);
}

test('valid request returns 201 and dispatches job', function () {
    $payload = makeInboundPayload();

    $response = $this->postJson(
        route('ura.inbound-lead'),
        $payload,
        ['X-URA-API-Key' => 'test-ura-key-12345']
    );

    $response->assertStatus(201);
    $response->assertJsonFragment(['status' => 'queued']);

    Queue::assertPushed(SendInboundLeadWhatsAppJob::class, fn ($job) => $job->voiceInstanceId === $payload['voice_instance_id']
        && $job->phone === '+5511999998888');
});

test('accepts optional name and metadata', function () {
    $payload = makeInboundPayload([
        'name' => 'João Silva',
        'metadata' => ['source' => 'ura_test', 'campaign' => 'inss_2026'],
    ]);

    $response = $this->postJson(
        route('ura.inbound-lead'),
        $payload,
        ['X-URA-API-Key' => 'test-ura-key-12345']
    );

    $response->assertStatus(201);
});

test('missing api key returns 401', function () {
    $payload = makeInboundPayload();

    $response = $this->postJson(route('ura.inbound-lead'), $payload);

    $response->assertStatus(401);
    $response->assertJsonFragment(['error' => 'Unauthorized']);

    Queue::assertNotPushed(SendInboundLeadWhatsAppJob::class);
});

test('wrong api key returns 401', function () {
    $payload = makeInboundPayload();

    $response = $this->postJson(
        route('ura.inbound-lead'),
        $payload,
        ['X-URA-API-Key' => 'wrong-key-value']
    );

    $response->assertStatus(401);
    $response->assertJsonFragment(['error' => 'Unauthorized']);

    Queue::assertNotPushed(SendInboundLeadWhatsAppJob::class);
});

test('bearer token auth also accepted', function () {
    $payload = makeInboundPayload();

    $response = $this->postJson(
        route('ura.inbound-lead'),
        $payload,
        ['Authorization' => 'Bearer test-ura-key-12345']
    );

    $response->assertStatus(201);
});

test('missing phone returns 422', function () {
    $payload = makeInboundPayload();
    unset($payload['phone']);

    $response = $this->postJson(
        route('ura.inbound-lead'),
        $payload,
        ['X-URA-API-Key' => 'test-ura-key-12345']
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['phone']);

    Queue::assertNotPushed(SendInboundLeadWhatsAppJob::class);
});

test('invalid phone format returns 422', function () {
    $payload = makeInboundPayload(['phone' => '999999']);

    $response = $this->postJson(
        route('ura.inbound-lead'),
        $payload,
        ['X-URA-API-Key' => 'test-ura-key-12345']
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['phone']);

    Queue::assertNotPushed(SendInboundLeadWhatsAppJob::class);
});

test('non-existent voice_instance_id returns 422', function () {
    $payload = makeInboundPayload(['voice_instance_id' => 99999]);

    $response = $this->postJson(
        route('ura.inbound-lead'),
        $payload,
        ['X-URA-API-Key' => 'test-ura-key-12345']
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['voice_instance_id']);

    Queue::assertNotPushed(SendInboundLeadWhatsAppJob::class);
});
