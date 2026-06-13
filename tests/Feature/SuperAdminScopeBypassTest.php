<?php

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('super-admin with no tenant memberships sees all leads across tenants', function () {
    // Arrange: two separate tenants each with one lead
    $tenantA = Tenant::create(['name' => 'Tenant A']);
    $tenantB = Tenant::create(['name' => 'Tenant B']);

    Lead::factory()->create(['tenant_id' => $tenantA->id]);
    Lead::factory()->create(['tenant_id' => $tenantB->id]);

    // Create a super-admin with no tenant memberships
    $superAdmin = User::factory()->create();
    $superAdmin->tenants()->detach();
    $superAdmin->is_super_admin = true;
    $superAdmin->save();

    // Act: authenticate as super-admin and count all leads
    $this->actingAs($superAdmin);

    $count = Lead::count();

    // Assert: super-admin sees all rows, not zero
    expect($count)->toBe(2);
});

it('normal tenant owner only sees their own tenant leads', function () {
    // Arrange: two separate tenants each with one lead
    $tenantA = Tenant::create(['name' => 'Tenant A']);
    $tenantB = Tenant::create(['name' => 'Tenant B']);

    Lead::factory()->create(['tenant_id' => $tenantA->id]);
    Lead::factory()->create(['tenant_id' => $tenantB->id]);

    // Create a normal owner scoped to tenantA
    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenantA->id, ['role' => \App\Enums\TenantRole::Owner->value]);

    // Act: authenticate as normal owner with active_tenant_id in session
    $this->actingAs($owner)->withSession(['active_tenant_id' => $tenantA->id]);

    $count = Lead::count();

    // Assert: normal owner only sees tenantA's 1 lead
    expect($count)->toBe(1);
});
