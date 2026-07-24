<?php

use App\Models\Agent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// ── SC4: read-only tenant list ────────────────────────────────────────────────

it('super-admin GET tenants.index returns 200 with tenant rows', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $tenant = Tenant::create(['name' => 'Org A']);
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $tenant->users()->attach($u1->id, ['role' => 'owner']);
    $tenant->users()->attach($u2->id, ['role' => 'user']);

    // Use an existing user_id to avoid creating extra tenants via AgentFactory
    Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $u1->id]);
    Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $u1->id]);
    Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $u1->id]);

    $captured = [];

    $this->actingAs($admin)
        ->get(route('backoffice.tenants.index'))
        ->assertOk()
        ->assertInertia(function ($page) use (&$captured) {
            $page->component('backoffice/tenants/Index')
                ->has('tenants');
            $captured = $page->toArray()['props']['tenants'] ?? [];
        });

    $orgA = collect($captured)->firstWhere('name', 'Org A');

    expect($orgA)->not->toBeNull()
        ->and($orgA['agents_count'])->toBe(3);
});

it('tenant row exposes name, users_count, agents_count and created_at', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $tenant = Tenant::create(['name' => 'Org B']);
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $tenant->users()->attach($u1->id, ['role' => 'owner']);
    $tenant->users()->attach($u2->id, ['role' => 'user']);

    // Use existing user_id to avoid factory creating extra tenants
    Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $u1->id]);
    Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $u1->id]);
    Agent::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $u1->id]);

    $captured = [];

    $this->actingAs($admin)
        ->get(route('backoffice.tenants.index'))
        ->assertOk()
        ->assertInertia(function ($page) use (&$captured) {
            $page->has('tenants');
            $captured = $page->toArray()['props']['tenants'] ?? [];
        });

    $orgB = collect($captured)->firstWhere('name', 'Org B');

    expect($orgB)->not->toBeNull()
        ->and($orgB)->toHaveKeys(['name', 'users_count', 'agents_count', 'created_at'])
        ->and($orgB['users_count'])->toBeGreaterThanOrEqual(2)
        ->and($orgB['agents_count'])->toBe(3);
});

it('keeps agent counts complete while acting as one company', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $orgA = Tenant::create(['name' => 'Org A']);
    $orgB = Tenant::create(['name' => 'Org B']);

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Agent::factory()->create(['tenant_id' => $orgA->id, 'user_id' => $userA->id]);
    Agent::factory()->create(['tenant_id' => $orgB->id, 'user_id' => $userB->id]);
    Agent::factory()->create(['tenant_id' => $orgB->id, 'user_id' => $userB->id]);

    $captured = [];

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => (string) $orgA->id])
        ->get(route('backoffice.tenants.index'))
        ->assertOk()
        ->assertInertia(function ($page) use (&$captured) {
            $captured = $page->toArray()['props']['tenants'] ?? [];
        });

    expect(collect($captured)->firstWhere('name', 'Org A')['agents_count'])->toBe(1)
        ->and(collect($captured)->firstWhere('name', 'Org B')['agents_count'])->toBe(2);
});

// ── SC4 read-only: no mutation routes exist ───────────────────────────────────

it('no mutating tenant routes are registered', function () {
    expect(Route::has('backoffice.tenants.update'))->toBeFalse();
    expect(Route::has('backoffice.tenants.store'))->toBeFalse();
    expect(Route::has('backoffice.tenants.destroy'))->toBeFalse();
});

it('POST to backoffice tenants returns 404 or 405', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $response = $this->actingAs($admin)->post('/backoffice/tenants', []);

    expect($response->status())->toBeIn([404, 405]);
});

// ── Privilege-escalation guard ────────────────────────────────────────────────

it('non-super-admin GET tenants.index receives 403', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('backoffice.tenants.index'))
        ->assertForbidden();
});
