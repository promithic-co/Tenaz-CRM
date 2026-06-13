<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('test_agents_index_includes_metrics', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);

    Lead::factory()->forAgent($agent)->count(2)->create(['status' => 'novo']);
    Lead::factory()->forAgent($agent)->count(1)->create(['status' => 'qualificado']);
    Lead::factory()->forAgent($agent)->count(1)->create(['status' => 'convertido']);
    Lead::factory()->forAgent($agent)->count(1)->create(['status' => 'desqualificado']);

    $this->actingAs($user)
        ->get(route('agentes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('agentes/Index')
            ->has('agents', 1, fn ($agentData) => $agentData
                ->where('id', $agent->id)
                ->where('leads_count', 5)
                ->where('active_conversations', 3)
                ->where('converted_count', 1)
                ->where('conversion_rate', 20)
                ->etc()
            )
        );
});

test('agents index includes available instances', function () {
    $user = User::factory()->create();
    Agent::factory()->create(['user_id' => $user->id]);
    WhatsappInstance::factory()->create(['user_id' => $user->id, 'agent_id' => null]);

    $this->actingAs($user)
        ->get(route('agentes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('agentes/Index')
            ->has('available_instances', 1)
        );
});

test('update instance links a free instance to agent', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $instance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'agent_id' => null]);

    $this->actingAs($user)
        ->patch(route('agentes.instance.update', $agent), ['whatsapp_instance_id' => $instance->id])
        ->assertRedirect(route('agentes.index'));

    expect($instance->fresh()->agent_id)->toBe($agent->id);
});

test('update instance unlinks previous instance when switching', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $oldInstance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'agent_id' => $agent->id]);
    $newInstance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'agent_id' => null]);

    $this->actingAs($user)
        ->patch(route('agentes.instance.update', $agent), ['whatsapp_instance_id' => $newInstance->id])
        ->assertRedirect(route('agentes.index'));

    expect($oldInstance->fresh()->agent_id)->toBeNull();
    expect($newInstance->fresh()->agent_id)->toBe($agent->id);
});

test('update instance desvinculates when null is sent', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $instance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'agent_id' => $agent->id]);

    $this->actingAs($user)
        ->patch(route('agentes.instance.update', $agent), ['whatsapp_instance_id' => null])
        ->assertRedirect(route('agentes.index'));

    expect($instance->fresh()->agent_id)->toBeNull();
});

test('update instance rejects instance belonging to another user', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $otherInstance = WhatsappInstance::factory()->create(['agent_id' => null]); // outro usuário

    $this->actingAs($user)
        ->patch(route('agentes.instance.update', $agent), ['whatsapp_instance_id' => $otherInstance->id])
        ->assertSessionHasErrors('whatsapp_instance_id');
});

test('update instance returns 404 for agent of another tenant', function () {
    $user = User::factory()->create();
    $otherAgent = Agent::factory()->create(); // outro tenant
    $instance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'agent_id' => null]);

    $this->actingAs($user)
        ->patch(route('agentes.instance.update', $otherAgent), ['whatsapp_instance_id' => $instance->id])
        ->assertNotFound();
});

test('update instance with valid free instance sets is_active true', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'is_active' => false]);
    $instance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'agent_id' => null]);

    $this->actingAs($user)
        ->patch(route('agentes.instance.update', $agent), ['whatsapp_instance_id' => $instance->id])
        ->assertRedirect(route('agentes.index'));

    expect($agent->fresh()->is_active)->toBeTrue();
    expect($instance->fresh()->agent_id)->toBe($agent->id);
});

test('update instance with null sets is_active false and unlinks instance', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'is_active' => true]);
    $instance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'agent_id' => $agent->id]);

    $this->actingAs($user)
        ->patch(route('agentes.instance.update', $agent), ['whatsapp_instance_id' => null])
        ->assertRedirect(route('agentes.index'));

    expect($agent->fresh()->is_active)->toBeFalse();
    expect($instance->fresh()->agent_id)->toBeNull();
});

test('update instance with already-linked instance id is rejected and preserves old assignment', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);
    $currentInstance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'agent_id' => $agent->id]);

    // Another agent claims the candidate instance
    $otherAgent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);
    $takenInstance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'agent_id' => $otherAgent->id]);

    $this->actingAs($user)
        ->patch(route('agentes.instance.update', $agent), ['whatsapp_instance_id' => $takenInstance->id])
        ->assertSessionHasErrors('whatsapp_instance_id');

    // Old assignment preserved
    expect($currentInstance->fresh()->agent_id)->toBe($agent->id);
    expect($takenInstance->fresh()->agent_id)->toBe($otherAgent->id);
});

test('update instance from same user but different active tenant is rejected', function () {
    $user = User::factory()->create();

    // Add a second tenant and switch context
    $secondTenant = \App\Models\Tenant::create(['name' => 'Second Tenant']);
    $user->tenants()->attach($secondTenant->id, ['role' => \App\Enums\TenantRole::Owner->value]);

    // Agent belongs to the user's first (default) tenant
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);

    // Instance belongs to the SECOND tenant — not the active one
    $crossTenantInstance = \App\Models\WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => (string) $secondTenant->id,
        'agent_id' => null,
    ]);

    // Request attempts to link the cross-tenant instance (validation must block it)
    $this->actingAs($user)
        ->patch(route('agentes.instance.update', $agent), ['whatsapp_instance_id' => $crossTenantInstance->id])
        ->assertSessionHasErrors('whatsapp_instance_id');
});

test('manual deactivation of a linked agent is allowed', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'is_active' => true]);
    WhatsappInstance::factory()->create(['user_id' => $user->id, 'agent_id' => $agent->id]);

    $this->actingAs($user)
        ->patch(route('agentes.toggle-active', $agent))
        ->assertRedirect();

    expect($agent->fresh()->is_active)->toBeFalse();
});

// --- 18-01: Edit Agent Basics ---

test('update agent name and description', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'name' => 'Old Name']);

    $this->actingAs($user)
        ->patch(route('agentes.update', $agent), ['name' => 'New Name', 'description' => 'New desc'])
        ->assertRedirect();

    expect($agent->fresh())
        ->name->toBe('New Name')
        ->description->toBe('New desc');
});

test('update agent rejects duplicate name for same user', function () {
    $user = User::factory()->create();
    Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'name' => 'Taken']);
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'name' => 'Original']);

    $this->actingAs($user)
        ->patch(route('agentes.update', $agent), ['name' => 'Taken'])
        ->assertSessionHasErrors('name');
});

test('update agent allows keeping same name', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'name' => 'Same']);

    $this->actingAs($user)
        ->patch(route('agentes.update', $agent), ['name' => 'Same', 'description' => 'Updated'])
        ->assertRedirect();

    expect($agent->fresh()->description)->toBe('Updated');
});

test('update agent returns 404 for other tenant', function () {
    $user = User::factory()->create();
    $otherAgent = Agent::factory()->create(); // different tenant

    $this->actingAs($user)
        ->patch(route('agentes.update', $otherAgent), ['name' => 'Hack'])
        ->assertNotFound();
});

// --- 18-02: Agent Deactivation Toggle ---

test('toggle active deactivates an active agent', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'is_active' => true]);

    $this->actingAs($user)
        ->patch(route('agentes.toggle-active', $agent))
        ->assertRedirect();

    expect($agent->fresh()->is_active)->toBeFalse();
});

test('toggle active activates an inactive agent when it has a linked instance', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'is_active' => false]);
    // An agent may only be activated when a WhatsApp instance is linked (T-60-06, D-14)
    WhatsappInstance::factory()->create(['user_id' => $user->id, 'agent_id' => $agent->id]);

    $this->actingAs($user)
        ->patch(route('agentes.toggle-active', $agent))
        ->assertRedirect();

    expect($agent->fresh()->is_active)->toBeTrue();
});

test('toggle active rejects activation of agent with no linked instance', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'is_active' => false]);
    // No instance linked — activation must be rejected

    $this->actingAs($user)
        ->patch(route('agentes.toggle-active', $agent))
        ->assertSessionHasErrors('toggle');

    expect($agent->fresh()->is_active)->toBeFalse();
});

test('toggle active returns 404 for other tenant', function () {
    $user = User::factory()->create();
    $otherAgent = Agent::factory()->create();

    $this->actingAs($user)
        ->patch(route('agentes.toggle-active', $otherAgent))
        ->assertNotFound();
});

// --- 18-03: Agent Archive & Restore ---

test('destroy agent soft deletes and unlinks instance', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);
    $instance = WhatsappInstance::factory()->create(['user_id' => $user->id, 'agent_id' => $agent->id]);

    $this->actingAs($user)
        ->delete(route('agentes.destroy', $agent))
        ->assertRedirect(route('agentes.index'));

    expect(Agent::withTrashed()->find($agent->id)->deleted_at)->not->toBeNull();
    expect($instance->fresh()->agent_id)->toBeNull();
});

test('restore agent brings it back', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);
    $agent->delete();

    $this->actingAs($user)
        ->patch(route('agentes.restore', $agent->id))
        ->assertRedirect(route('agentes.index'));

    expect(Agent::find($agent->id))->not->toBeNull();
    expect(Agent::find($agent->id)->deleted_at)->toBeNull();
});

test('restore agent returns 404 for other tenant', function () {
    $user = User::factory()->create();
    $otherAgent = Agent::factory()->create();
    $otherAgent->delete();

    $this->actingAs($user)
        ->patch(route('agentes.restore', $otherAgent->id))
        ->assertNotFound();
});

test('archived agents appear in index', function () {
    $user = User::factory()->create();
    $active = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'name' => 'Active']);
    $archived = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId, 'name' => 'Archived']);
    $archived->delete();

    $this->actingAs($user)
        ->get(route('agentes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('agentes/Index')
            ->has('agents', 1)
            ->has('archived_agents', 1, fn ($a) => $a
                ->where('name', 'Archived')
                ->etc()
            )
        );
});

test('test_conversion_rate_is_zero_when_no_leads', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('agentes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('agentes/Index')
            ->has('agents', 1, fn ($agentData) => $agentData
                ->where('id', $agent->id)
                ->where('leads_count', 0)
                ->where('active_conversations', 0)
                ->where('converted_count', 0)
                ->where('conversion_rate', 0)
                ->etc()
            )
        );
});

test('agents index query count does not scale with agent count', function () {
    $user = User::factory()->create();

    $measure = function () use ($user): int {
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($user)->get(route('agentes.index'))->assertOk();
        $count = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        return $count;
    };

    // Baseline with a single agent (+ leads to exercise the aggregates).
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    Lead::factory()->forAgent($agent)->count(2)->create(['status' => 'novo']);
    Lead::factory()->forAgent($agent)->count(1)->create(['status' => 'convertido']);
    $oneAgentQueries = $measure();

    // Three more agents, each with their own leads.
    for ($i = 0; $i < 3; $i++) {
        $extra = Agent::factory()->create(['user_id' => $user->id]);
        Lead::factory()->forAgent($extra)->count(2)->create(['status' => 'novo']);
        Lead::factory()->forAgent($extra)->count(1)->create(['status' => 'convertido']);
    }
    $fourAgentQueries = $measure();

    // Aggregates fold into the agent fetch — adding agents must not add a
    // per-agent leads query (the N+1 the refactor removes).
    expect($fourAgentQueries)->toBe($oneAgentQueries);
});

test('owner can assign an agent to a tenant user', function () {
    $owner = User::factory()->create();
    $member = \App\Models\User::factory()->create();
    $member->tenants()->detach();
    $member->tenants()->attach($owner->tenantId, ['role' => \App\Enums\TenantRole::User->value]);

    $agent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $owner->tenantId]);

    $this->actingAs($owner)
        ->patch(route('agentes.assign', $agent), ['user_id' => $member->id])
        ->assertRedirect();

    expect($agent->fresh()->user_id)->toBe($member->id);
});

test('assign returns 403 for a non-owner non-admin user', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $member->tenants()->detach();
    $member->tenants()->attach($owner->tenantId, ['role' => \App\Enums\TenantRole::User->value]);

    $agent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $owner->tenantId]);

    $this->actingAs($member)
        ->patch(route('agentes.assign', $agent), ['user_id' => $member->id])
        ->assertForbidden();
});

test('assign returns 404 for an agent in another tenant', function () {
    $owner = User::factory()->create();
    $otherAgent = Agent::factory()->create(); // different tenant

    $this->actingAs($owner)
        ->patch(route('agentes.assign', $otherAgent), ['user_id' => null])
        ->assertNotFound();
});
