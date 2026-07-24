<?php

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// ── URL prefix ────────────────────────────────────────────────────────────────

it('mounts the backoffice under the configured path', function () {
    config(['backoffice.path' => 'zona-secreta']);

    // Register the route file onto a throwaway router so the assertion sees the
    // prefix this environment would produce, not the one booted for the suite.
    $router = new Router(app('events'), app());
    app()->instance('router', $router);
    Route::clearResolvedInstances();

    require base_path('routes/backoffice.php');

    $routes = $router->getRoutes();
    $routes->refreshNameLookups();

    expect($routes->getByName('backoffice.index')->uri())->toBe('zona-secreta')
        ->and($routes->getByName('backoffice.tenants.index')->uri())->toBe('zona-secreta/tenants');
});

// ── Switching ─────────────────────────────────────────────────────────────────

it('super-admin can select the company to act as', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $tenant = Tenant::create(['name' => 'Org A']);

    $this->actingAs($admin)
        ->post(route('backoffice.active-tenant.store'), ['tenant_id' => (string) $tenant->id])
        ->assertRedirect();

    expect(session('active_tenant_id'))->toBe((string) $tenant->id);
});

it('rejects selecting a company that does not exist', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $this->actingAs($admin)
        ->post(route('backoffice.active-tenant.store'), ['tenant_id' => '999999'])
        ->assertSessionHasErrors('tenant_id');

    expect(session()->has('active_tenant_id'))->toBeFalse();
});

it('super-admin can go back to the global view', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $tenant = Tenant::create(['name' => 'Org A']);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->delete(route('backoffice.active-tenant.destroy'))
        ->assertRedirect();

    expect(session()->has('active_tenant_id'))->toBeFalse();
});

it('non-super-admin cannot switch the active company', function () {
    $user = User::factory()->create();
    $tenant = Tenant::create(['name' => 'Org A']);

    $this->actingAs($user)
        ->post(route('backoffice.active-tenant.store'), ['tenant_id' => (string) $tenant->id])
        ->assertForbidden();
});

it('guest cannot switch the active company', function () {
    $tenant = Tenant::create(['name' => 'Org A']);

    $this->post(route('backoffice.active-tenant.store'), ['tenant_id' => (string) $tenant->id])
        ->assertRedirect();
});

// ── Cross-tenant isolation ────────────────────────────────────────────────────

it('scopes the whole app to the selected company', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);

    Lead::factory()->create(['tenant_id' => $tenantA->id]);
    Lead::factory()->create(['tenant_id' => $tenantB->id]);
    Lead::factory()->create(['tenant_id' => $tenantB->id]);

    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $this->actingAs($admin)->withSession(['active_tenant_id' => (string) $tenantA->id]);

    expect(Lead::count())->toBe(1)
        ->and((string) Lead::first()->tenant_id)->toBe((string) $tenantA->id);
});

it('never leaks rows from another company while acting as one', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);

    $leadB = Lead::factory()->create(['tenant_id' => $tenantB->id]);

    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $this->actingAs($admin)->withSession(['active_tenant_id' => (string) $tenantA->id]);

    expect(Lead::find($leadB->id))->toBeNull();
});

it('keeps the cross-tenant view when no company is selected', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);

    Lead::factory()->create(['tenant_id' => $tenantA->id]);
    Lead::factory()->create(['tenant_id' => $tenantB->id]);

    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $this->actingAs($admin);

    expect(Lead::count())->toBe(2);
});

it('shows no rows to a tenantless normal user', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    Lead::factory()->create(['tenant_id' => $tenant->id]);

    $user = User::factory()->create();
    $user->tenants()->detach();

    $this->actingAs($user);

    expect(Lead::count())->toBe(0);
});
