<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Unit-level coverage for Lead::scopeVisibleTo — the tenant-isolation / triage
 * control extracted from ConversasController::buildInboxQuery (Plan B.1).
 *
 * @return array{Tenant, User, User}
 */
function scopeTenant(): array
{
    $tenant = Tenant::create(['name' => 'ScopeTenant']);

    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $restricted = User::factory()->create();
    $restricted->tenants()->detach();
    $restricted->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    return [$tenant, $owner, $restricted];
}

test('restricted user sees own-agent + assigned + unassigned-agentless only', function () {
    [$tenant, $owner, $restricted] = scopeTenant();

    $ownAgent = Agent::factory()->create(['user_id' => $restricted->id, 'tenant_id' => $tenant->id]);
    $ownAgentLead = Lead::factory()->forAgent($ownAgent)->create();

    $ownerAgent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id]);
    $assignedLead = Lead::factory()->forAgent($ownerAgent)->create([
        'assigned_user_id' => $restricted->id,
    ]);

    $unassignedLead = Lead::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'agent_id' => null,
        'assigned_user_id' => null,
    ]);

    $hiddenLead = Lead::factory()->forAgent($ownerAgent)->create([
        'assigned_user_id' => null,
    ]);

    $this->actingAs($restricted);

    $ids = Lead::production()
        ->forTenant((string) $tenant->id)
        ->visibleTo($restricted)
        ->pluck('id')
        ->all();

    expect($ids)->toContain($ownAgentLead->id)
        ->toContain($assignedLead->id)
        ->toContain($unassignedLead->id)
        ->not->toContain($hiddenLead->id);
});

test('owner (privileged) sees every lead in the tenant', function () {
    [$tenant, $owner, $restricted] = scopeTenant();

    $ownerAgent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id]);
    $restrictedAgent = Agent::factory()->create(['user_id' => $restricted->id, 'tenant_id' => $tenant->id]);

    $a = Lead::factory()->forAgent($ownerAgent)->create(['assigned_user_id' => null]);
    $b = Lead::factory()->forAgent($restrictedAgent)->create(['assigned_user_id' => null]);
    $c = Lead::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'agent_id' => null,
        'assigned_user_id' => null,
    ]);

    $this->actingAs($owner);

    $ids = Lead::production()
        ->forTenant((string) $tenant->id)
        ->visibleTo($owner)
        ->pluck('id')
        ->all();

    expect($ids)->toContain($a->id)->toContain($b->id)->toContain($c->id);
});

test('admin bypasses the visibility restriction', function () {
    $tenant = Tenant::create(['name' => 'AdminTenant']);

    $admin = User::factory()->create();
    $admin->tenants()->detach();
    $admin->tenants()->attach($tenant->id, ['role' => TenantRole::Administrator->value]);

    $other = User::factory()->create();
    $other->tenants()->detach();
    $other->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    $otherAgent = Agent::factory()->create(['user_id' => $other->id, 'tenant_id' => $tenant->id]);
    $foreignToAdmin = Lead::factory()->forAgent($otherAgent)->create(['assigned_user_id' => null]);

    $this->actingAs($admin);

    $ids = Lead::production()
        ->forTenant((string) $tenant->id)
        ->visibleTo($admin)
        ->pluck('id')
        ->all();

    expect($ids)->toContain($foreignToAdmin->id);
});

test('restricted user cannot see cross-tenant leads', function () {
    [$tenant, $owner, $restricted] = scopeTenant();

    $ownTenantLead = Lead::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'agent_id' => null,
        'assigned_user_id' => null,
    ]);

    $foreignTenant = Tenant::create(['name' => 'ForeignTenant']);
    $foreignOwner = User::factory()->create();
    $foreignOwner->tenants()->detach();
    $foreignOwner->tenants()->attach($foreignTenant->id, ['role' => TenantRole::Owner->value]);
    $foreignAgent = Agent::factory()->create(['user_id' => $foreignOwner->id, 'tenant_id' => $foreignTenant->id]);
    $foreignLead = Lead::factory()->forAgent($foreignAgent)->create();

    $this->actingAs($restricted);

    $ids = Lead::production()
        ->forTenant((string) $tenant->id)
        ->visibleTo($restricted)
        ->pluck('id')
        ->all();

    expect($ids)->toContain($ownTenantLead->id)
        ->not->toContain($foreignLead->id);
});
