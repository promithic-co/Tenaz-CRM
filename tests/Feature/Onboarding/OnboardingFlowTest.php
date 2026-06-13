<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a fresh incomplete owner with a single tenant, no prior agents.
 */
function makeWizardOwner(): User
{
    $user = User::factory()->notOnboarded()->create();
    // Detach auto-created tenant, attach a clean one
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Wizard Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    return $user->fresh();
}

/**
 * Create a free WhatsApp instance belonging to the given user+tenant.
 */
function makeFreeInstance(User $user): WhatsappInstance
{
    return WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'agent_id' => null,
    ]);
}

/**
 * Post a valid template slug to storeAgent and return the response.
 * Uses the first available slug from AgentTemplateService.
 */
function postAgent(User $user, ?string $slug = null): \Illuminate\Testing\TestResponse
{
    if ($slug === null) {
        $slug = app(\App\Services\AgentTemplateService::class)->slugs()[0] ?? 'alicia-receptivo';
    }

    return test()->actingAs($user)->post('/onboarding/agent', ['template_slug' => $slug]);
}

// ---------------------------------------------------------------------------
// Authorization guards — non-wizard users must not access wizard endpoints
// ---------------------------------------------------------------------------

test('completed owner cannot access GET /onboarding', function () {
    $user = User::factory()->create(); // onboarded_at = now()
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Done Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $this->actingAs($user)->get('/onboarding')->assertForbidden();
});

test('invited administrator cannot access GET /onboarding', function () {
    $user = User::factory()->notOnboarded()->create();
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Admin Auth Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Administrator->value]);

    $this->actingAs($user)->get('/onboarding')->assertForbidden();
});

test('regular user cannot access GET /onboarding', function () {
    $user = User::factory()->notOnboarded()->create();
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Regular User Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    $this->actingAs($user)->get('/onboarding')->assertForbidden();
});

test('super-admin cannot access GET /onboarding', function () {
    $admin = User::factory()->superAdmin()->notOnboarded()->create();
    $admin->tenants()->detach();
    $tenant = Tenant::create(['name' => 'SA Onboard Tenant']);
    $admin->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $this->actingAs($admin)->get('/onboarding')->assertForbidden();
});

test('completed owner cannot POST /onboarding/agent', function () {
    $user = User::factory()->create();
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Done Post Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $this->actingAs($user)->post('/onboarding/agent', ['template_slug' => 'vendas'])->assertForbidden();
});

test('administrator cannot POST /onboarding/persona', function () {
    $user = User::factory()->notOnboarded()->create();
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Admin Persona Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Administrator->value]);

    $this->actingAs($user)->post('/onboarding/persona', [
        'agent_name' => 'Test',
        'company_name' => 'Test Co',
        'agent_personality' => 'Friendly',
        'agent_greeting' => 'Hello!',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// Step derivation from GET /onboarding
// ---------------------------------------------------------------------------

test('GET /onboarding with no draft pointer renders step 1', function () {
    $owner = makeWizardOwner();

    $this->actingAs($owner)
        ->get('/onboarding')
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/Index')
            ->where('current_step', 'template')
        );
});

test('GET /onboarding with draft pointer but no instance or skip renders step 2', function () {
    $owner = makeWizardOwner();
    postAgent($owner);

    $this->actingAs($owner->fresh())
        ->get('/onboarding')
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/Index')
            ->where('current_step', 'instance')
        );
});

test('GET /onboarding after explicit skip renders step 3 (persona)', function () {
    $owner = makeWizardOwner();
    postAgent($owner);
    test()->actingAs($owner->fresh())->post('/onboarding/instance', []);

    $this->actingAs($owner->fresh())
        ->get('/onboarding')
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/Index')
            ->where('current_step', 'persona')
        );
});

test('GET /onboarding after explicit instance link renders step 3 (persona)', function () {
    $owner = makeWizardOwner();
    $instance = makeFreeInstance($owner);
    postAgent($owner);
    test()->actingAs($owner->fresh())->post('/onboarding/instance', ['whatsapp_instance_id' => $instance->id]);

    $this->actingAs($owner->fresh())
        ->get('/onboarding')
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/Index')
            ->where('current_step', 'persona')
        );
});

// ---------------------------------------------------------------------------
// storeAgent: idempotent draft creation
// ---------------------------------------------------------------------------

test('POST /onboarding/agent creates a draft agent with server-owned initial values', function () {
    $owner = makeWizardOwner();

    postAgent($owner)->assertRedirect('/onboarding');

    $owner->refresh();
    expect($owner->onboarding_agent_id)->not->toBeNull();

    $agent = Agent::find($owner->onboarding_agent_id);
    expect($agent)->not->toBeNull();
    expect($agent->is_active)->toBeFalse(); // starts inactive
    expect($agent->tenant_id)->toBe($owner->tenantId);
});

test('draft agent config is seeded from template defaults', function () {
    $owner = makeWizardOwner();
    $slug = app(\App\Services\AgentTemplateService::class)->slugs()[0] ?? 'alicia-receptivo';
    postAgent($owner, $slug);

    $owner->refresh();
    $config = AgentConfig::where('agent_id', $owner->onboarding_agent_id)->first();
    expect($config)->not->toBeNull();
    expect($config->template_slug)->toBe($slug);
});

test('repeated POST /onboarding/agent reuses the same draft (no duplicate agents)', function () {
    $owner = makeWizardOwner();

    postAgent($owner);
    $owner->refresh();
    $firstAgentId = $owner->onboarding_agent_id;
    $agentCountAfterFirst = Agent::count();

    postAgent($owner->fresh());
    $owner->refresh();

    expect($owner->onboarding_agent_id)->toBe($firstAgentId);
    expect(Agent::count())->toBe($agentCountAfterFirst);
});

test('storeAgent initializes onboarding_whatsapp_skipped_at to null on new pointer', function () {
    $owner = makeWizardOwner();
    // Manually set skipped_at to simulate a stale value
    DB::table('users')->where('id', $owner->id)->update(['onboarding_whatsapp_skipped_at' => now()]);

    postAgent($owner->fresh());

    expect($owner->fresh()->onboarding_whatsapp_skipped_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// storeInstance: skip (Fazer depois) behavior
// ---------------------------------------------------------------------------

test('skip instance persists onboarding_whatsapp_skipped_at and leaves draft inactive', function () {
    $owner = makeWizardOwner();
    postAgent($owner);

    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);

    $owner->refresh();
    expect($owner->onboarding_whatsapp_skipped_at)->not->toBeNull();
    expect(Agent::find($owner->onboarding_agent_id)->is_active)->toBeFalse();
});

test('skip is idempotent: repeated skip POST does not change existing skip timestamp', function () {
    $owner = makeWizardOwner();
    postAgent($owner);
    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);

    $owner->refresh();
    $firstSkippedAt = $owner->onboarding_whatsapp_skipped_at;

    // Second skip
    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);

    expect($owner->fresh()->onboarding_whatsapp_skipped_at->eq($firstSkippedAt))->toBeTrue();
});

// ---------------------------------------------------------------------------
// storeInstance: explicit free-instance link
// ---------------------------------------------------------------------------

test('explicit free-instance link activates draft and clears skip marker', function () {
    $owner = makeWizardOwner();
    $instance = makeFreeInstance($owner);
    postAgent($owner);

    // Skip first, then link (allowed if not yet linked)
    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);
    $owner->refresh();
    expect($owner->onboarding_whatsapp_skipped_at)->not->toBeNull();

    // Link instance — this is not a "skip-after-link" scenario; no link exists yet
    $this->actingAs($owner->fresh())->post('/onboarding/instance', [
        'whatsapp_instance_id' => $instance->id,
    ]);

    $owner->refresh();
    expect($owner->onboarding_whatsapp_skipped_at)->toBeNull();
    $agent = Agent::find($owner->onboarding_agent_id);
    expect($agent->is_active)->toBeTrue();
    expect($agent->whatsappInstance->id)->toBe($instance->id);
});

test('skip-after-link is rejected: marker stays null after link', function () {
    $owner = makeWizardOwner();
    $instance = makeFreeInstance($owner);
    postAgent($owner);

    // Link first
    $this->actingAs($owner->fresh())->post('/onboarding/instance', [
        'whatsapp_instance_id' => $instance->id,
    ]);
    $owner->refresh();
    expect($owner->onboarding_whatsapp_skipped_at)->toBeNull();

    // Now try to skip — should be rejected (link already exists)
    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);

    // Marker must remain null; link must be intact
    $owner->refresh();
    expect($owner->onboarding_whatsapp_skipped_at)->toBeNull();
    $agent = Agent::find($owner->onboarding_agent_id);
    expect($agent->is_active)->toBeTrue();
    expect($agent->whatsappInstance()->exists())->toBeTrue();
});

test('different instance after link is rejected without stealing', function () {
    $owner = makeWizardOwner();
    $instance1 = makeFreeInstance($owner);
    $instance2 = makeFreeInstance($owner);
    postAgent($owner);

    // Link instance1
    $this->actingAs($owner->fresh())->post('/onboarding/instance', [
        'whatsapp_instance_id' => $instance1->id,
    ]);

    // Try to link instance2 — must be rejected
    $response = $this->actingAs($owner->fresh())->post('/onboarding/instance', [
        'whatsapp_instance_id' => $instance2->id,
    ]);

    // instance2 must remain free
    expect($instance2->fresh()->agent_id)->toBeNull();
    // instance1 must still be linked
    expect($instance1->fresh()->agent_id)->toBe($owner->fresh()->onboarding_agent_id);
});

// ---------------------------------------------------------------------------
// Cross-tenant and IDOR protections
// ---------------------------------------------------------------------------

test('cross-tenant instance is rejected by validation (T-60-02)', function () {
    $owner = makeWizardOwner();
    postAgent($owner);

    // Create a free instance belonging to a different user/tenant
    $otherUser = User::factory()->create();
    $foreignInstance = WhatsappInstance::factory()->create([
        'user_id' => $otherUser->id,
        'tenant_id' => $otherUser->tenantId,
        'agent_id' => null,
    ]);

    $this->actingAs($owner->fresh())->post('/onboarding/instance', [
        'whatsapp_instance_id' => $foreignInstance->id,
    ])->assertSessionHasErrors('whatsapp_instance_id');

    // Foreign instance must remain free
    expect($foreignInstance->fresh()->agent_id)->toBeNull();
});

test('forged non-null onboarding_agent_id pointing to cross-tenant agent on GET /onboarding returns 404', function () {
    $owner = makeWizardOwner();

    // Create a real agent on a different tenant and point the owner's pointer at it
    $otherUser = User::factory()->create();
    $foreignAgent = Agent::where('tenant_id', $otherUser->tenantId)->first()
        ?? Agent::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $otherUser->tenantId,
        ]);

    DB::table('users')->where('id', $owner->id)->update(['onboarding_agent_id' => $foreignAgent->id]);

    $this->actingAs($owner->fresh())->get('/onboarding')->assertNotFound();
});

test('cross-tenant onboarding_agent_id on storeAgent returns 403', function () {
    $owner = makeWizardOwner();
    $otherUser = User::factory()->create();
    // Point onboarding_agent_id to another tenant's agent
    $foreignAgent = Agent::where('tenant_id', $otherUser->tenantId)->first();
    if (! $foreignAgent) {
        $foreignAgent = Agent::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $otherUser->tenantId,
        ]);
    }
    DB::table('users')->where('id', $owner->id)->update(['onboarding_agent_id' => $foreignAgent->id]);

    $validSlug = app(\App\Services\AgentTemplateService::class)->slugs()[0] ?? 'alicia-receptivo';
    $this->actingAs($owner->fresh())->post('/onboarding/agent', ['template_slug' => $validSlug])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// storePersona: completion
// ---------------------------------------------------------------------------

test('storePersona sets onboarded_at, clears pointer and skip marker, redirects to complete', function () {
    $owner = makeWizardOwner();
    postAgent($owner);
    $this->actingAs($owner->fresh())->post('/onboarding/instance', []); // skip

    $agentId = $owner->fresh()->onboarding_agent_id;

    $response = $this->actingAs($owner->fresh())->post('/onboarding/persona', [
        'agent_name' => 'Aria Vendas',
        'company_name' => 'Minha Empresa',
        'agent_personality' => 'Amigável e profissional',
        'agent_greeting' => 'Olá! Como posso ajudar?',
    ]);

    $response->assertRedirect('/onboarding/complete/'.$agentId);

    $owner->refresh();
    expect($owner->onboarded_at)->not->toBeNull();
    expect($owner->onboarding_agent_id)->toBeNull();
    expect($owner->onboarding_whatsapp_skipped_at)->toBeNull();
});

test('storePersona persists exactly the 4 persona fields onto AgentConfig', function () {
    $owner = makeWizardOwner();
    postAgent($owner);
    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);

    $agentId = $owner->fresh()->onboarding_agent_id;

    $this->actingAs($owner->fresh())->post('/onboarding/persona', [
        'agent_name' => 'Nome Custom',
        'company_name' => 'Empresa Custom',
        'agent_personality' => 'Formal',
        'agent_greeting' => 'Bem-vindo!',
    ]);

    $config = AgentConfig::where('agent_id', $agentId)->first();
    expect($config->agent_name)->toBe('Nome Custom');
    expect($config->company_name)->toBe('Empresa Custom');
    expect($config->agent_personality)->toBe('Formal');
    expect($config->agent_greeting)->toBe('Bem-vindo!');
});

test('late instance submit after persona completion cannot mutate state', function () {
    $owner = makeWizardOwner();
    $instance = makeFreeInstance($owner);
    postAgent($owner);
    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);

    // Complete persona
    $this->actingAs($owner->fresh())->post('/onboarding/persona', [
        'agent_name' => 'Done',
        'company_name' => 'Done Co',
        'agent_personality' => 'OK',
        'agent_greeting' => 'Hi!',
    ]);

    // Late instance submit — owner is now completed, so wizard endpoints are forbidden
    $this->actingAs($owner->fresh())->post('/onboarding/instance', [
        'whatsapp_instance_id' => $instance->id,
    ])->assertForbidden();

    expect($instance->fresh()->agent_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// complete() endpoint
// ---------------------------------------------------------------------------

test('GET /onboarding/complete/{agent} returns 200 for owner with their agent', function () {
    $owner = makeWizardOwner();
    postAgent($owner);
    $owner->refresh();
    $agentId = $owner->onboarding_agent_id;

    // Skip then complete persona (sets onboarded_at)
    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);
    $this->actingAs($owner->fresh())->post('/onboarding/persona', [
        'agent_name' => 'Done',
        'company_name' => 'Done Co',
        'agent_personality' => 'OK',
        'agent_greeting' => 'Hi!',
    ]);

    $this->actingAs($owner->fresh())
        ->get('/onboarding/complete/'.$agentId)
        ->assertOk();
});

test('complete endpoint derives is_ready as false when no instance linked', function () {
    $owner = makeWizardOwner();
    postAgent($owner);
    $owner->refresh();
    $agentId = $owner->onboarding_agent_id;

    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);
    $this->actingAs($owner->fresh())->post('/onboarding/persona', [
        'agent_name' => 'Done',
        'company_name' => 'Done Co',
        'agent_personality' => 'OK',
        'agent_greeting' => 'Hi!',
    ]);

    $this->actingAs($owner->fresh())
        ->get('/onboarding/complete/'.$agentId)
        ->assertInertia(fn ($page) => $page
            ->where('is_ready', false)
        );
});

test('complete endpoint derives is_ready as true when instance is linked and active', function () {
    $owner = makeWizardOwner();
    $instance = makeFreeInstance($owner);
    postAgent($owner);
    $owner->refresh();
    $agentId = $owner->onboarding_agent_id;

    $this->actingAs($owner->fresh())->post('/onboarding/instance', [
        'whatsapp_instance_id' => $instance->id,
    ]);
    $this->actingAs($owner->fresh())->post('/onboarding/persona', [
        'agent_name' => 'Done',
        'company_name' => 'Done Co',
        'agent_personality' => 'OK',
        'agent_greeting' => 'Hi!',
    ]);

    $this->actingAs($owner->fresh())
        ->get('/onboarding/complete/'.$agentId)
        ->assertInertia(fn ($page) => $page
            ->where('is_ready', true)
        );
});

test('cross-tenant completion agent returns 403', function () {
    $owner = makeWizardOwner();

    $otherUser = User::factory()->create();
    $foreignAgent = Agent::where('tenant_id', $otherUser->tenantId)->first()
        ?? Agent::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $otherUser->tenantId,
        ]);

    // Complete onboarding so we can reach /complete
    postAgent($owner);
    $this->actingAs($owner->fresh())->post('/onboarding/instance', []);
    $this->actingAs($owner->fresh())->post('/onboarding/persona', [
        'agent_name' => 'X',
        'company_name' => 'X',
        'agent_personality' => 'X',
        'agent_greeting' => 'X',
    ]);

    $this->actingAs($owner->fresh())
        ->get('/onboarding/complete/'.$foreignAgent->id)
        ->assertForbidden();
});
