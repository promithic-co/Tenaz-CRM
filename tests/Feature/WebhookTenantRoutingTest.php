<?php

use App\Models\Tenant;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('webhook resolves correct tenant instance by instance name', function () {
    $userA = userWithTenant();
    $tenantA = $userA->tenants()->first();

    $userB = userWithTenant();
    $tenantB = $userB->tenants()->first();

    WhatsappInstance::factory()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $userA->id,
        'name' => 'aria_tenant_a',
        'provider' => 'meta_cloud',
    ]);
    WhatsappInstance::factory()->create([
        'tenant_id' => $tenantB->id,
        'user_id' => $userB->id,
        'name' => 'aria_tenant_b',
        'provider' => 'meta_cloud',
    ]);

    // Simulate unscoped webhook lookup (as the controller does)
    $found = WhatsappInstance::withoutGlobalScope('tenant')
        ->where('name', 'aria_tenant_a')
        ->first();

    expect($found)->not->toBeNull();
    expect($found->tenant_id)->toBe((string) $tenantA->id);
});

it('webhook does not mix up instances from different tenants', function () {
    $userA = userWithTenant();
    $tenantA = $userA->tenants()->first();

    $userB = userWithTenant();
    $tenantB = $userB->tenants()->first();

    WhatsappInstance::factory()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $userA->id,
        'name' => 'instance-a',
        'provider' => 'meta_cloud',
    ]);
    WhatsappInstance::factory()->create([
        'tenant_id' => $tenantB->id,
        'user_id' => $userB->id,
        'name' => 'instance-b',
        'provider' => 'meta_cloud',
    ]);

    $foundA = WhatsappInstance::withoutGlobalScope('tenant')->where('name', 'instance-a')->first();
    $foundB = WhatsappInstance::withoutGlobalScope('tenant')->where('name', 'instance-b')->first();

    expect($foundA->tenant_id)->toBe((string) $tenantA->id);
    expect($foundB->tenant_id)->toBe((string) $tenantB->id);
    expect($foundA->tenant_id)->not->toBe($foundB->tenant_id);
});

it('authenticated user cannot see instances from another tenant', function () {
    $userA = userWithTenant();
    $tenantA = $userA->tenants()->first();

    $userB = userWithTenant();

    WhatsappInstance::factory()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $userA->id,
        'name' => 'instance-a',
    ]);

    // BelongsToTenant scope prevents tenant B from seeing tenant A's instance
    $this->actingAs($userB);
    expect(WhatsappInstance::query()->count())->toBe(0);
});
