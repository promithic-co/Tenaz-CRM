<?php

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('super-admin gets 200 on backoffice', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $this->actingAs($admin)
        ->get(route('backoffice.templates.index'))
        ->assertOk();
});

it('non-super-admin owner gets 403 on backoffice', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('backoffice.index'))
        ->assertForbidden();
});

it('guest cannot access backoffice', function () {
    $this->get(route('backoffice.index'))
        ->assertRedirect();
});

it('tenantless super-admin sees all leads across tenants', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $t1 = Tenant::create(['name' => 'Tenant A']);
    $t2 = Tenant::create(['name' => 'Tenant B']);

    Lead::factory()->create(['tenant_id' => $t1->id]);
    Lead::factory()->create(['tenant_id' => $t2->id]);

    $this->actingAs($admin);

    expect(Lead::count())->toBe(2);
});

it('keeps the active tenant selection on backoffice entry', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $tenant = Tenant::create(['name' => 'Tenant A']);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->get(route('backoffice.templates.index'))
        ->assertOk();

    expect(session('active_tenant_id'))->toBe((string) $tenant->id);
});

it('drops a selection pointing at a tenant that no longer exists', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => '999999'])
        ->get(route('backoffice.templates.index'))
        ->assertOk();

    expect(session()->has('active_tenant_id'))->toBeFalse();
});

it('exposes auth.is_super_admin in inertia props', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    $this->actingAs($admin)
        ->get(route('backoffice.templates.index'))
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/templates/Index')
            ->where('auth.is_super_admin', true)
        );
});
