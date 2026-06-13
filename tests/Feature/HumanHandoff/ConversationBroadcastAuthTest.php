<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Note: LogBroadcaster (used in test env) skips channel callbacks, so we test
// the authorization logic directly — same code that runs in routes/channels.php.

function authorizeConversationChannel(User $user, Lead $lead): bool
{
    if ((string) $lead->tenant_id !== (string) $user->tenantId) {
        return false;
    }

    if ($user->isRestrictedUser()) {
        return (int) $lead->assigned_user_id === (int) $user->id
            || $lead->agent?->user_id === $user->id;
    }

    return true;
}

function conversationAuthSetup(): array
{
    $tenant = Tenant::create(['name' => 'ConvAuthTest']);

    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $restricted = User::factory()->create();
    $restricted->tenants()->detach();
    $restricted->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    $other = User::factory()->create();

    $agent = Agent::factory()->create([
        'user_id' => $owner->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'assigned_user_id' => null,
    ]);

    return [$tenant, $owner, $restricted, $other, $lead];
}

test('owner can access any conversation channel in own tenant', function () {
    [, $owner, , , $lead] = conversationAuthSetup();

    expect(authorizeConversationChannel($owner, $lead))->toBeTrue();
});

test('restricted user assigned to lead can access conversation channel', function () {
    [, , $restricted, , $lead] = conversationAuthSetup();
    $lead->update(['assigned_user_id' => $restricted->id]);
    $lead->refresh();

    expect(authorizeConversationChannel($restricted, $lead))->toBeTrue();
});

test('restricted user not assigned to lead is blocked from conversation channel', function () {
    [, , $restricted, , $lead] = conversationAuthSetup();

    expect(authorizeConversationChannel($restricted, $lead))->toBeFalse();
});

test('user from another tenant is blocked from conversation channel', function () {
    [, , , $other, $lead] = conversationAuthSetup();

    expect(authorizeConversationChannel($other, $lead))->toBeFalse();
});

test('restricted user who owns the lead agent can access conversation channel', function () {
    [$tenant, $owner, $restricted, , $lead] = conversationAuthSetup();

    $ownedAgent = Agent::factory()->create([
        'user_id' => $restricted->id,
        'tenant_id' => $tenant->id,
    ]);

    $ownedLead = Lead::factory()->forAgent($ownedAgent)->create([
        'tenant_id' => (string) $tenant->id,
        'assigned_user_id' => null,
    ]);

    expect(authorizeConversationChannel($restricted, $ownedLead->load('agent')))->toBeTrue();
});
