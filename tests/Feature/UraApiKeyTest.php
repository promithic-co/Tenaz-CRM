<?php

use App\Jobs\SendUraTemplateJob;
use App\Models\Agent;
use App\Models\UraApiKey;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

// ─── /api/ura/trigger authentication ─────────────────────────────────────────

test('trigger rejects missing api key', function () {
    $this->postJson(route('ura.trigger'), ['phone' => '+5511999998888'])
        ->assertStatus(401)
        ->assertJsonFragment(['error' => 'Unauthorized']);

    Queue::assertNotPushed(SendUraTemplateJob::class);
});

test('trigger rejects unknown api key', function () {
    $this->postJson(
        route('ura.trigger'),
        ['phone' => '+5511999998888'],
        ['X-URA-API-Key' => 'ura_'.str_repeat('x', 40)]
    )->assertStatus(401);

    Queue::assertNotPushed(SendUraTemplateJob::class);
});

test('trigger rejects inactive api key', function () {
    $tenant = userWithTenant();
    $instance = WhatsappInstance::factory()->create(['tenant_id' => $tenant->id]);
    $agent = Agent::factory()->create(['tenant_id' => $tenant->id]);
    $instance->update(['agent_id' => $agent->id]);

    $generated = UraApiKey::generate();
    UraApiKey::create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'name' => 'Test key',
        'key_hash' => $generated['key_hash'],
        'key_preview' => $generated['key_preview'],
        'active' => false,
    ]);

    $this->postJson(
        route('ura.trigger'),
        ['phone' => '+5511999998888'],
        ['X-URA-API-Key' => $generated['key']]
    )->assertStatus(401);

    Queue::assertNotPushed(SendUraTemplateJob::class);
});

// ─── /api/ura/trigger dispatching ────────────────────────────────────────────

test('trigger rejects legacy config key because it is not bound to a db api key', function () {
    config(['services.ura.api_key' => 'legacy-secret']);

    $this->postJson(
        route('ura.trigger'),
        ['phone' => '+5511999998888'],
        ['X-URA-API-Key' => 'legacy-secret']
    )->assertStatus(401)
        ->assertJsonFragment(['error' => 'Unauthorized']);

    Queue::assertNotPushed(SendUraTemplateJob::class);
});

test('trigger with valid key returns 201 and dispatches job', function () {
    $tenant = userWithTenant();
    $instance = WhatsappInstance::factory()->create(['tenant_id' => $tenant->id]);
    $agent = Agent::factory()->create(['tenant_id' => $tenant->id]);
    $instance->update(['agent_id' => $agent->id]);

    $generated = UraApiKey::generate();
    $apiKey = UraApiKey::create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'name' => 'Discador INSS',
        'key_hash' => $generated['key_hash'],
        'key_preview' => $generated['key_preview'],
        'active' => true,
    ]);

    $this->postJson(
        route('ura.trigger'),
        ['phone' => '+5511999998888', 'name' => 'João'],
        ['X-URA-API-Key' => $generated['key']]
    )->assertStatus(201)->assertJsonFragment(['status' => 'queued']);

    Queue::assertPushed(SendUraTemplateJob::class, function ($job) use ($apiKey) {
        return $job->uraApiKeyId === $apiKey->id
            && $job->phone === '+5511999998888'
            && $job->name === 'João';
    });
});

test('trigger passes variables to job', function () {
    $tenant = userWithTenant();
    $agent = Agent::factory()->create(['tenant_id' => $tenant->id]);

    $generated = UraApiKey::generate();
    $apiKey = UraApiKey::create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'name' => 'Test',
        'key_hash' => $generated['key_hash'],
        'key_preview' => $generated['key_preview'],
        'active' => true,
    ]);

    $this->postJson(
        route('ura.trigger'),
        ['phone' => '+5511999998888', 'variables' => ['João', 'INSS']],
        ['X-URA-API-Key' => $generated['key']]
    )->assertStatus(201);

    Queue::assertPushed(SendUraTemplateJob::class, function ($job) {
        return $job->variables === ['João', 'INSS'];
    });
});

test('trigger updates last_used_at on the api key', function () {
    $tenant = userWithTenant();
    $agent = Agent::factory()->create(['tenant_id' => $tenant->id]);

    $generated = UraApiKey::generate();
    $apiKey = UraApiKey::create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'name' => 'Test',
        'key_hash' => $generated['key_hash'],
        'key_preview' => $generated['key_preview'],
        'active' => true,
        'last_used_at' => null,
    ]);

    $this->postJson(
        route('ura.trigger'),
        ['phone' => '+5511999998888'],
        ['X-URA-API-Key' => $generated['key']]
    )->assertStatus(201);

    expect($apiKey->fresh()->last_used_at)->not->toBeNull();
});

test('trigger debounces recent last_used_at writes on the api key', function () {
    config(['credflow.api.ura_key_last_used_debounce_seconds' => 300]);

    $tenant = userWithTenant();
    $agent = Agent::factory()->create(['tenant_id' => $tenant->id]);
    $recent = now()->subMinute()->startOfSecond();

    $generated = UraApiKey::generate();
    $apiKey = UraApiKey::create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'name' => 'Test',
        'key_hash' => $generated['key_hash'],
        'key_preview' => $generated['key_preview'],
        'active' => true,
        'last_used_at' => $recent,
    ]);

    $this->postJson(
        route('ura.trigger'),
        ['phone' => '+5511999998888'],
        ['X-URA-API-Key' => $generated['key']]
    )->assertStatus(201);

    expect($apiKey->fresh()->last_used_at->equalTo($recent))->toBeTrue();
});

test('trigger refreshes stale last_used_at on the api key', function () {
    config(['credflow.api.ura_key_last_used_debounce_seconds' => 300]);

    $tenant = userWithTenant();
    $agent = Agent::factory()->create(['tenant_id' => $tenant->id]);
    $stale = now()->subMinutes(10)->startOfSecond();

    $generated = UraApiKey::generate();
    $apiKey = UraApiKey::create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'name' => 'Test',
        'key_hash' => $generated['key_hash'],
        'key_preview' => $generated['key_preview'],
        'active' => true,
        'last_used_at' => $stale,
    ]);

    $this->postJson(
        route('ura.trigger'),
        ['phone' => '+5511999998888'],
        ['X-URA-API-Key' => $generated['key']]
    )->assertStatus(201);

    expect($apiKey->fresh()->last_used_at->greaterThan($stale))->toBeTrue();
});

test('trigger rejects invalid phone format', function () {
    $tenant = userWithTenant();
    $agent = Agent::factory()->create(['tenant_id' => $tenant->id]);

    $generated = UraApiKey::generate();
    UraApiKey::create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'name' => 'Test',
        'key_hash' => $generated['key_hash'],
        'key_preview' => $generated['key_preview'],
        'active' => true,
    ]);

    $this->postJson(
        route('ura.trigger'),
        ['phone' => '99999'],
        ['X-URA-API-Key' => $generated['key']]
    )->assertStatus(422)->assertJsonValidationErrors(['phone']);

    Queue::assertNotPushed(SendUraTemplateJob::class);
});

// ─── UraApiKey model helpers ──────────────────────────────────────────────────

test('store rejects agent and template from another tenant', function () {
    $tenant = userWithTenant();
    $otherTenant = userWithTenant();

    $foreignAgent = Agent::factory()->create(['tenant_id' => $otherTenant->id]);
    $foreignTemplate = WhatsappTemplate::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($tenant)
        ->post(route('ura.store'), [
            'name' => 'Discador externo',
            'agent_id' => $foreignAgent->id,
            'whatsapp_template_id' => $foreignTemplate->id,
        ])
        ->assertSessionHasErrors(['agent_id', 'whatsapp_template_id']);

    expect(UraApiKey::count())->toBe(0);
});

test('update rejects agent and template from another tenant', function () {
    $tenant = userWithTenant();
    $otherTenant = userWithTenant();

    $agent = Agent::factory()->create(['tenant_id' => $tenant->id]);
    $apiKey = UraApiKey::factory()->create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
    ]);

    $foreignAgent = Agent::factory()->create(['tenant_id' => $otherTenant->id]);
    $foreignTemplate = WhatsappTemplate::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($tenant)
        ->patch(route('ura.update', $apiKey), [
            'agent_id' => $foreignAgent->id,
            'whatsapp_template_id' => $foreignTemplate->id,
        ])
        ->assertSessionHasErrors(['agent_id', 'whatsapp_template_id']);

    $apiKey->refresh();
    expect($apiKey->agent_id)->toBe($agent->id)
        ->and($apiKey->whatsapp_template_id)->toBeNull();
});

test('generate returns key with ura_ prefix and correct hash', function () {
    $result = UraApiKey::generate();

    expect($result['key'])->toStartWith('ura_');
    expect($result['key_hash'])->toBe(hash('sha256', $result['key']));
    expect($result['key_preview'])->toBe(substr($result['key'], -8));
});

test('findByPlainKey returns null for unknown key', function () {
    expect(UraApiKey::findByPlainKey('ura_unknown_key'))->toBeNull();
});

test('findByPlainKey returns null for inactive key', function () {
    $tenant = userWithTenant();
    $agent = Agent::factory()->create(['tenant_id' => $tenant->id]);

    $generated = UraApiKey::generate();
    UraApiKey::create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'name' => 'Inactive',
        'key_hash' => $generated['key_hash'],
        'key_preview' => $generated['key_preview'],
        'active' => false,
    ]);

    expect(UraApiKey::findByPlainKey($generated['key']))->toBeNull();
});
