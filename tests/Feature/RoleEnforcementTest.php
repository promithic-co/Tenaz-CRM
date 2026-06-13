<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seatMember(Tenant $tenant, string $role): User
{
    $user = User::factory()->create();
    // Remove the auto-created tenant so the test's explicit tenant is the active one
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => $role]);

    return $user;
}

it('restricted user cannot view another user agent', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $owner = seatMember($tenant, TenantRole::Owner->value);
    $member = seatMember($tenant, TenantRole::User->value);

    $othersAgent = Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);

    expect($member->can('view', $othersAgent))->toBeFalse();
});

it('restricted user can view their own agent', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $member = seatMember($tenant, TenantRole::User->value);

    $ownAgent = Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $member->id]);

    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);

    expect($member->can('view', $ownAgent))->toBeTrue();
});

it('administrator sees all tenant agents', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $admin = seatMember($tenant, TenantRole::Administrator->value);
    $other = seatMember($tenant, TenantRole::User->value);

    $agent = Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $other->id]);

    $this->actingAs($admin)->withSession(['active_tenant_id' => $tenant->id]);

    expect($admin->can('view', $agent))->toBeTrue();
});

it('restricted user cannot access campaigns route', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $member = seatMember($tenant, TenantRole::User->value);

    $this->actingAs($member)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/campanhas')
        ->assertForbidden();
});

it('restricted user lead scope hides other users leads via agent ownership', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $owner = seatMember($tenant, TenantRole::Owner->value);
    $member = seatMember($tenant, TenantRole::User->value);

    $ownersAgent = Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $ownersLead = Lead::factory()->create(['tenant_id' => $tenant->id, 'agent_id' => $ownersAgent->id]);

    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);

    expect($member->can('view', $ownersLead))->toBeFalse();
});

it('restricted user can triage unassigned agentless crm lead but cannot delete it', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $member = seatMember($tenant, TenantRole::User->value);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'agent_id' => null,
        'assigned_user_id' => null,
    ]);

    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);

    expect($member->can('view', $lead))->toBeTrue()
        ->and($member->can('update', $lead))->toBeTrue()
        ->and($member->can('delete', $lead))->toBeFalse();

    $this->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.total', 1)
            ->where('leads.data.0.id', $lead->id)
        );
});

it('restricted user cannot see agentless lead assigned to another operator', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $assigned = seatMember($tenant, TenantRole::User->value);
    $member = seatMember($tenant, TenantRole::User->value);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'agent_id' => null,
        'assigned_user_id' => $assigned->id,
    ]);

    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);

    expect($member->can('view', $lead))->toBeFalse();

    $this->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.total', 0)
        );
});

it('restricted user can claim an unassigned agentless conversation', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $member = seatMember($tenant, TenantRole::User->value);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'agent_id' => null,
        'assigned_user_id' => null,
        'operational_stage' => Lead::STAGE_HUMAN_PENDING,
    ]);

    $this->actingAs($member)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('conversas.claim', $lead))
        ->assertRedirect();

    $lead->refresh();

    expect($lead->assigned_user_id)->toBe($member->id)
        ->and($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_ACTIVE)
        ->and($lead->ai_paused_by)->toBe($member->id)
        ->and($lead->ai_paused_reason)->toBe('ticket_claimed');

    $this->assertDatabaseHas('agent_interaction_events', [
        'lead_id' => $lead->id,
        'event_type' => 'handoff_claimed',
        'event_source' => 'conversas_controller',
    ]);
});

it('owner cannot be demoted via team endpoint', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $owner = seatMember($tenant, TenantRole::Owner->value);

    $this->actingAs($owner)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->patch("/settings/team/members/{$owner->id}", ['role' => TenantRole::User->value])
        ->assertSessionHasErrors('role');
});

it('owner cannot be removed from tenant', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $owner = seatMember($tenant, TenantRole::Owner->value);
    $admin = seatMember($tenant, TenantRole::Administrator->value);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete("/settings/team/members/{$owner->id}")
        ->assertSessionHasErrors('user');
});

it('restricted user can view CRM-triage lead with no agent and no assignee', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $member = seatMember($tenant, TenantRole::User->value);

    $triageLead = Lead::factory()->withoutAgent()->create([
        'tenant_id' => $tenant->id,
        'assigned_user_id' => null,
    ]);

    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);

    expect($member->can('view', $triageLead))->toBeTrue();
});

it('restricted user can assume an unassigned conversation', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $member = seatMember($tenant, TenantRole::User->value);

    $triageLead = Lead::factory()->withoutAgent()->create([
        'tenant_id' => $tenant->id,
        'assigned_user_id' => null,
    ]);

    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);

    $this->post(route('conversas.assume', $triageLead))->assertRedirect();

    expect($triageLead->fresh()->assigned_user_id)->toBe($member->id)
        ->and($triageLead->fresh()->operational_stage)->toBe(Lead::STAGE_HUMAN_ACTIVE);
});

it('restricted user cannot assume a lead already assigned to someone else', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $member = seatMember($tenant, TenantRole::User->value);
    $other = seatMember($tenant, TenantRole::User->value);

    $assigned = Lead::factory()->withoutAgent()->create([
        'tenant_id' => $tenant->id,
        'assigned_user_id' => $other->id,
    ]);

    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);

    // Policy is intentionally permissive (same-tenant); the lifecycle service
    // enforces the assignment conflict, so the attempt is rejected with a
    // redirect-with-errors and the lead is never reassigned to the intruder.
    $this->post(route('conversas.assume', $assigned))
        ->assertSessionHasErrors();

    expect($assigned->fresh()->assigned_user_id)->toBe($other->id);
});

it('restricted user cannot view other user whatsapp instance', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $other = seatMember($tenant, TenantRole::User->value);
    $member = seatMember($tenant, TenantRole::User->value);

    $instance = WhatsappInstance::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $other->id]);

    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);

    expect($member->can('view', $instance))->toBeFalse();
});
