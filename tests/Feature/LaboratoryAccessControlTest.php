<?php

use App\Enums\TenantRole;
use App\Models\AiUsageDaily;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Laboratory and Playground expose infrastructure health, AI cost and an LLM
 * sandbox billed to the platform account. They are operator tooling gated by
 * `super_admin`, so every tenant role — owner included — must be refused, and
 * refused on the route itself rather than only hidden from the sidebar.
 */
function tenantUserWithRole(TenantRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->sync([]);

    $tenant = Tenant::create(['name' => 'Empresa '.$role->value]);
    $user->tenants()->attach($tenant->id, ['role' => $role->value]);

    return $user->fresh();
}

/** @return list<string> */
function laboratoryGetRoutes(): array
{
    return [
        route('backoffice.laboratory'),
        route('backoffice.laboratory.datasets'),
        route('backoffice.laboratory.stress-test'),
        route('backoffice.laboratory.ai-usage'),
        route('backoffice.laboratory.health'),
        route('backoffice.laboratory.datasets.index'),
        route('backoffice.laboratory.stress-tests.index'),
        route('backoffice.playground.index'),
    ];
}

it('refuses laboratory and playground to every tenant role', function (TenantRole $role) {
    $user = tenantUserWithRole($role);

    expect($user->currentRole())->toBe($role);

    foreach (laboratoryGetRoutes() as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }
})->with([
    'owner' => TenantRole::Owner,
    'administrator' => TenantRole::Administrator,
    'user' => TenantRole::User,
]);

it('refuses laboratory write endpoints to a tenant owner', function () {
    $user = tenantUserWithRole(TenantRole::Owner);

    $this->actingAs($user)
        ->post(route('backoffice.laboratory.datasets.store'), [])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('backoffice.laboratory.stress-tests.store'), [])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('backoffice.playground.store'), [])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('backoffice.playground.scanBlindspots'), [])
        ->assertForbidden();
});

it('allows laboratory and playground for a super-admin', function () {
    $user = User::factory()->superAdmin()->create();

    foreach (laboratoryGetRoutes() as $url) {
        $this->actingAs($user)->get($url)->assertSuccessful();
    }
});

it('redirects guests to login instead of leaking a 403', function () {
    $this->get(route('backoffice.laboratory'))->assertRedirect(route('login'));
    $this->get(route('backoffice.playground.index'))->assertRedirect(route('login'));
});

it('no longer serves laboratory or playground at their old top-level paths', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get('/laboratory')->assertNotFound();
    $this->actingAs($user)->get('/laboratory/health')->assertNotFound();
    $this->actingAs($user)->get('/playground')->assertNotFound();
});

it('scopes laboratory reads to the backoffice active tenant', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $superAdmin->tenants()->sync([]);

    $tenantA = Tenant::create(['name' => 'Empresa A']);
    $tenantB = Tenant::create(['name' => 'Empresa B']);

    AiUsageDaily::factory()->create([
        'tenant_id' => $tenantA->id,
        'date' => now()->toDateString(),
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => (string) $tenantA->id])
        ->get(route('backoffice.laboratory.ai-usage'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/AiUsage')
            ->has('dailyUsage', 1)
        );

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => (string) $tenantB->id])
        ->get(route('backoffice.laboratory.ai-usage'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/AiUsage')
            ->has('dailyUsage', 0)
        );
});

it('shares the active tenant so laboratory pages can show their scope', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::create(['name' => 'Empresa Escopo']);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->get(route('backoffice.laboratory'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('backoffice.active_tenant.name', 'Empresa Escopo')
        );
});
