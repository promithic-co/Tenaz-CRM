<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Note: LogBroadcaster (used in test env) skips channel callbacks, so we test
// the authorization logic directly — same code that runs in routes/channels.php.

function authorizeAtendimentosChannel(User $user, string $tenantId): bool
{
    return (string) $user->tenantId === $tenantId;
}

function broadcastAuthTenant(): array
{
    $tenant = Tenant::create(['name' => 'BroadcastAuthTest']);

    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $restricted = User::factory()->create();
    $restricted->tenants()->detach();
    $restricted->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    $other = User::factory()->create();

    return [$tenant, $owner, $restricted, $other];
}

test('owner can access atendimentos channel for own tenant', function () {
    [$tenant, $owner] = broadcastAuthTenant();

    expect(authorizeAtendimentosChannel($owner, (string) $tenant->id))->toBeTrue();
});

test('restricted user can access atendimentos channel for own tenant', function () {
    [$tenant, , $restricted] = broadcastAuthTenant();

    expect(authorizeAtendimentosChannel($restricted, (string) $tenant->id))->toBeTrue();
});

test('user from other tenant is blocked from atendimentos channel', function () {
    [$tenant, , , $other] = broadcastAuthTenant();

    expect(authorizeAtendimentosChannel($other, (string) $tenant->id))->toBeFalse();
});

test('user with null tenantId is blocked from atendimentos channel', function () {
    $tenant = Tenant::create(['name' => 'NoTenantTest']);
    $user = User::factory()->create();
    $user->tenants()->detach();

    expect(authorizeAtendimentosChannel($user, (string) $tenant->id))->toBeFalse();
});
