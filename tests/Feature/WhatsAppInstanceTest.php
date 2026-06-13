<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Guests ───────────────────────────────────────────────────────────────────

test('guests are redirected from whatsapp index', function () {
    $this->get(route('whatsapp.index'))->assertRedirect(route('login'));
});

test('guests cannot access instance status', function () {
    $instance = WhatsappInstance::factory()->create();
    $this->getJson(route('whatsapp.status', $instance))->assertUnauthorized();
});

// ─── Index ────────────────────────────────────────────────────────────────────

test('authenticated users can visit whatsapp page', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('whatsapp.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('whatsapp/Index')
            ->has('instances')
        );
});

test('index only shows instances belonging to authenticated user', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
    ]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    WhatsappInstance::factory()->for($userA)->create(['name' => 'instancia-a']);
    WhatsappInstance::factory()->for($userB)->create(['name' => 'instancia-b']);

    $this->actingAs($userA)
        ->get(route('whatsapp.index'))
        ->assertInertia(fn ($page) => $page
            ->where('instances.0.name', 'instancia-a')
            ->count('instances', 1)
        );
});

// ─── Store ────────────────────────────────────────────────────────────────────

test('user can create a meta cloud instance via embedded signup', function () {
    Http::fake([
        'graph.facebook.com/*/register' => Http::response([], 200),
        'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true], 200),
    ]);

    $user = User::factory()->create();
    $token = str_repeat('b', 64);

    Cache::put("meta_signup:{$token}", [
        'access_token' => 'test-token',
        'system_user_id' => null,
        'permanent' => true,
        'waba_id' => 'waba-456',
        'phone_number_id' => 'phone-456',
        'mode' => 'new',
        'meta_pin' => '000000',
    ], now()->addMinutes(30));

    $this->actingAs($user)
        ->post(route('whatsapp.store'), [
            'display_name' => 'Vendas SP',
            'name' => 'vendas-sp',
            'provider' => 'meta_cloud',
            'meta_signup_token' => $token,
        ])
        ->assertRedirect();

    expect(WhatsappInstance::where('user_id', $user->id)->where('name', 'vendas-sp')->exists())->toBeTrue();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('whatsapp.store'), [])
        ->assertSessionHasErrors(['name']);
});

test('store prevents duplicate instance name for same user', function () {
    $user = User::factory()->create();

    WhatsappInstance::factory()->for($user)->create(['name' => 'minha-instancia']);

    $this->actingAs($user)
        ->post(route('whatsapp.store'), [
            'name' => 'minha-instancia',
        ])
        ->assertSessionHasErrors(['name']);
});

// ─── Destroy ─────────────────────────────────────────────────────────────────

test('meta cloud store persists token lifetime from embedded signup cache', function () {
    Http::fake([
        'graph.facebook.com/*/register' => Http::response([], 200),
        'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true], 200),
    ]);

    $user = userWithTenant();
    $token = str_repeat('a', 64);

    Cache::put("meta_signup:{$token}", [
        'access_token' => 'temporary-token',
        'system_user_id' => null,
        'permanent' => false,
        'waba_id' => 'waba-123',
        'phone_number_id' => 'phone-123',
        'mode' => 'new',
        'meta_pin' => '123456',
    ], now()->addMinutes(30));

    $this->actingAs($user)
        ->post(route('whatsapp.store'), [
            'display_name' => 'Meta Vendas',
            'name' => 'meta-vendas',
            'provider' => 'meta_cloud',
            'meta_signup_token' => $token,
            'meta_pin' => '123456',
        ])
        ->assertRedirect();

    $instance = WhatsappInstance::where('name', 'meta-vendas')->first();
    expect($instance->meta_token_permanent)->toBeFalse();
    expect($instance->meta_token_expires_at)->not->toBeNull();
});

test('user can delete their own instance', function () {
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('whatsapp.destroy', $instance))
        ->assertRedirect();

    expect(WhatsappInstance::find($instance->id))->toBeNull();
});

test('user cannot delete another tenants instance', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $instance = WhatsappInstance::factory()->for($userB)->create();

    $this->actingAs($userA)
        ->delete(route('whatsapp.destroy', $instance))
        ->assertNotFound();
});

// ─── Status ───────────────────────────────────────────────────────────────────

test('status returns json with state', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'open'], 200),
    ]);

    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson(route('whatsapp.status', $instance))
        ->assertSuccessful()
        ->assertJsonPath('state', 'open');
});

test('status returns close when meta cloud instance has no access token', function () {
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->for($user)->create([
        'meta_access_token' => null,
    ]);

    $this->actingAs($user)
        ->getJson(route('whatsapp.status', $instance))
        ->assertSuccessful()
        ->assertJsonPath('state', 'close');
});

test('user cannot check status of another tenants instance', function () {
    Http::fake();

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $instance = WhatsappInstance::factory()->for($userB)->create();

    $this->actingAs($userA)
        ->getJson(route('whatsapp.status', $instance))
        ->assertNotFound();
});

// ─── Connect ─────────────────────────────────────────────────────────────────

test('connect for meta cloud returns error directing to embedded signup', function () {
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('whatsapp.connect', $instance))
        ->assertSuccessful()
        ->assertJsonStructure(['error']);
});

// ─── Disconnect ───────────────────────────────────────────────────────────────

test('disconnect logs out the instance', function () {
    Http::fake([
        '*/instance/logout/*' => Http::response([], 200),
    ]);

    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('whatsapp.disconnect', $instance))
        ->assertSuccessful()
        ->assertJsonPath('success', true);
});

test('user cannot disconnect another tenants instance', function () {
    Http::fake();

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $instance = WhatsappInstance::factory()->for($userB)->create();

    $this->actingAs($userA)
        ->postJson(route('whatsapp.disconnect', $instance))
        ->assertNotFound();
});

// ─── Index payload — extended instance details ───────────────────────────────

test('index payload includes meta cloud details and agent info', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
    ]);

    $user = userWithTenant();
    $agent = Agent::factory()->for($user)->create(['tenant_id' => $user->tenantId, 'name' => 'Agente Vendas']);

    WhatsappInstance::factory()->for($user)->create([
        'tenant_id' => $user->tenantId,
        'name' => 'meta-vendas',
        'provider' => 'meta_cloud',
        'agent_id' => $agent->id,
        'default_ai_mode' => 'automatic',
        'meta_waba_id' => 'waba-xyz',
        'meta_phone_number_id' => 'phone-xyz',
        'meta_quality_rating' => 'GREEN',
        'meta_token_permanent' => true,
        'meta_token_expires_at' => null,
        'meta_coexistence' => false,
    ]);

    $this->actingAs($user)
        ->get(route('whatsapp.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('whatsapp/Index')
            ->where('instances.0.meta_waba_id', 'waba-xyz')
            ->where('instances.0.meta_phone_number_id', 'phone-xyz')
            ->where('instances.0.meta_quality_rating', 'GREEN')
            ->where('instances.0.meta_token_permanent', true)
            ->where('instances.0.meta_coexistence', false)
            ->where('instances.0.agent_id', $agent->id)
            ->where('instances.0.agent_name', 'Agente Vendas')
            ->where('instances.0.default_ai_mode', 'automatic')
            ->where('instances.0.leads_count', 0)
            ->where('instances.0.has_proxy', false)
        );
});

test('index payload never exposes meta access token', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
    ]);

    $user = userWithTenant();

    WhatsappInstance::factory()->for($user)->create([
        'tenant_id' => $user->tenantId,
        'provider' => 'meta_cloud',
        'meta_access_token' => 'super-secret-token-value',
    ]);

    $response = $this->actingAs($user)->get(route('whatsapp.index'));

    expect($response->getContent())->not->toContain('super-secret-token-value');
    expect($response->getContent())->not->toContain('meta_access_token');
});

test('index payload counts leads via agent relation', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
    ]);

    $user = userWithTenant();
    $agent = Agent::factory()->for($user)->create(['tenant_id' => $user->tenantId]);
    $otherAgent = Agent::factory()->for($user)->create(['tenant_id' => $user->tenantId]);

    WhatsappInstance::factory()->for($user)->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
    ]);

    Lead::factory()->count(3)->forAgent($agent)->create();
    Lead::factory()->count(5)->forAgent($otherAgent)->create();

    $this->actingAs($user)
        ->get(route('whatsapp.index'))
        ->assertInertia(fn ($page) => $page
            ->where('instances.0.leads_count', 3)
        );
});

test('index payload returns zero leads when instance has no agent', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
    ]);

    $user = userWithTenant();

    WhatsappInstance::factory()->for($user)->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('whatsapp.index'))
        ->assertInertia(fn ($page) => $page
            ->where('instances.0.leads_count', 0)
            ->where('instances.0.agent_id', null)
            ->where('instances.0.agent_name', null)
        );
});
