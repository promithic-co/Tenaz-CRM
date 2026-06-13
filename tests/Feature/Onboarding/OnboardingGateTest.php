<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper: create an incomplete tenant owner (onboarded_at = null, no super-admin)
// ---------------------------------------------------------------------------
function makeIncompleteOwner(): User
{
    $user = User::factory()->notOnboarded()->create();
    $tenant = Tenant::create(['name' => 'Gate Test Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    return $user;
}

function makeCompletedOwner(): User
{
    $user = User::factory()->create(); // onboarded_at = now() by default
    $tenant = Tenant::create(['name' => 'Completed Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    return $user;
}

// ---------------------------------------------------------------------------
// Gate: EnsureOnboarded redirect behavior
// ---------------------------------------------------------------------------

test('incomplete owner is redirected from dashboard to onboarding', function () {
    $owner = makeIncompleteOwner();

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertRedirect('/onboarding');
});

test('completed owner reaches dashboard (200)', function () {
    $owner = makeCompletedOwner();

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertOk();
});

test('invited administrator bypasses gate and reaches dashboard', function () {
    $user = User::factory()->notOnboarded()->create();
    // Detach the auto-created owner tenant so tenantId resolves to the admin tenant below
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Admin Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Administrator->value]);

    $this->actingAs($user->fresh())
        ->get('/dashboard')
        ->assertOk();
});

test('regular user bypasses gate and reaches dashboard', function () {
    $user = User::factory()->notOnboarded()->create();
    // Detach the auto-created owner tenant so tenantId resolves to the user tenant below
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'User Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    $this->actingAs($user->fresh())
        ->get('/dashboard')
        ->assertOk();
});

test('super-admin bypasses gate even with null onboarded_at', function () {
    $admin = User::factory()->superAdmin()->notOnboarded()->create();
    $tenant = Tenant::create(['name' => 'Super Admin Tenant']);
    $admin->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk();
});

test('gated owner can GET /whatsapp (no redirect loop)', function () {
    $owner = makeIncompleteOwner();

    $this->actingAs($owner)
        ->get('/whatsapp')
        ->assertOk();
});

test('incomplete owner is redirected from settings/profile to onboarding', function () {
    $owner = makeIncompleteOwner();

    $this->actingAs($owner)
        ->get('/settings/profile')
        ->assertRedirect('/onboarding');
});

test('completed owner can access settings/profile', function () {
    $owner = makeCompletedOwner();

    $this->actingAs($owner)
        ->get('/settings/profile')
        ->assertOk();
});

test('invited administrator can access settings/profile without onboarding', function () {
    $user = User::factory()->notOnboarded()->create();
    // Detach the auto-created owner tenant so tenantId resolves to the admin tenant below
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Admin Settings Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Administrator->value]);

    $this->actingAs($user->fresh())
        ->get('/settings/profile')
        ->assertOk();
});

// --- Column and cast assertions ---

test('onboarded_at column exists on users table', function () {
    expect(Schema::hasColumn('users', 'onboarded_at'))->toBeTrue();
});

test('onboarding_agent_id column exists on users table', function () {
    expect(Schema::hasColumn('users', 'onboarding_agent_id'))->toBeTrue();
});

test('onboarding_whatsapp_skipped_at column exists on users table', function () {
    expect(Schema::hasColumn('users', 'onboarding_whatsapp_skipped_at'))->toBeTrue();
});

// --- Factory defaults ---

test('default factory user has onboarded_at set', function () {
    $user = User::factory()->create();

    expect($user->onboarded_at)->not->toBeNull();
});

test('notOnboarded factory user has null onboarded_at', function () {
    $user = User::factory()->notOnboarded()->create();

    expect($user->onboarded_at)->toBeNull();
});

test('notOnboarded factory user has null onboarding_whatsapp_skipped_at', function () {
    $user = User::factory()->notOnboarded()->create();

    expect($user->onboarding_whatsapp_skipped_at)->toBeNull();
});

test('notOnboarded factory user has null onboarding_agent_id', function () {
    $user = User::factory()->notOnboarded()->create();

    expect($user->fresh()->onboarding_agent_id)->toBeNull();
});

// --- onboardingAgent relation ---

test('default factory user onboardingAgent is null', function () {
    $user = User::factory()->create();

    expect($user->onboardingAgent)->toBeNull();
});

test('onboarding_agent_id is not in fillable', function () {
    $user = new User();

    expect(in_array('onboarding_agent_id', $user->getFillable()))->toBeFalse();
});

test('onboarded_at is not in fillable', function () {
    $user = new User();

    expect(in_array('onboarded_at', $user->getFillable()))->toBeFalse();
});

test('onboarding_whatsapp_skipped_at is not in fillable', function () {
    $user = new User();

    expect(in_array('onboarding_whatsapp_skipped_at', $user->getFillable()))->toBeFalse();
});

// --- Backfill correctness ---
// Each test mirrors the migration backfill SQL to validate it covers the right scenarios.
// We reset onboarded_at manually then re-run the same SQL to simulate a fresh migration.

/**
 * Helper: re-run the migration's backfill SQL on the current test DB.
 */
function runBackfillSql(): void
{
    $ownerUserIds = DB::table('tenant_user')
        ->where('role', TenantRole::Owner->value)
        ->whereExists(function ($q) {
            $q->select(DB::raw(1))->from('agents')
                ->whereColumn('agents.tenant_id', 'tenant_user.tenant_id')
                ->whereNull('agents.deleted_at');
        })
        ->pluck('user_id')->unique()->all();

    if (! empty($ownerUserIds)) {
        DB::table('users')->whereIn('id', $ownerUserIds)->update(['onboarded_at' => now()]);
    }
}

test('backfill marks owner complete when tenant has an agent assigned to another member', function () {
    // Owner and a member share the same tenant
    $owner = User::factory()->notOnboarded()->create();
    $tenant = Tenant::create(['name' => 'Test Tenant A']);
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $member = User::factory()->notOnboarded()->create();
    $member->tenants()->attach($tenant->id, ['role' => TenantRole::Administrator->value]);

    // Agent belongs to the tenant but is created by the member, not the owner
    Agent::factory()->create([
        'user_id' => $member->id,
        'tenant_id' => (string) $tenant->id,
    ]);

    // Reset owner so we can verify the backfill sets it
    DB::table('users')->where('id', $owner->id)->update(['onboarded_at' => null]);

    runBackfillSql();

    expect($owner->fresh()->onboarded_at)->not->toBeNull();
});

test('backfill marks owner complete when tenant has an agent the owner created themselves', function () {
    $owner = User::factory()->notOnboarded()->create();
    $tenant = Tenant::create(['name' => 'Test Tenant B']);
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    // Agent created by the owner in the same tenant
    Agent::factory()->create([
        'user_id' => $owner->id,
        'tenant_id' => (string) $tenant->id,
    ]);

    DB::table('users')->where('id', $owner->id)->update(['onboarded_at' => null]);

    runBackfillSql();

    expect($owner->fresh()->onboarded_at)->not->toBeNull();
});

test('backfill does not mark owner complete when tenant has no agents', function () {
    $owner = User::factory()->notOnboarded()->create();
    $tenant = Tenant::create(['name' => 'Empty Tenant C']);
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    // No agents for this tenant
    DB::table('users')->where('id', $owner->id)->update(['onboarded_at' => null]);

    runBackfillSql();

    expect($owner->fresh()->onboarded_at)->toBeNull();
});
