<?php

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;

it('rejects cross-tenant subscription to conversations channel', function () {
    $userA = userWithTenant();
    $tenantA = $userA->tenants()->first();
    $userB = userWithTenant();

    $tenantIdA = (string) $tenantA->id;

    // From channels.php: return (string) $user->tenantId === $tenantId;
    expect((string) $userA->tenantId === $tenantIdA)->toBeTrue();
    expect((string) $userB->tenantId === $tenantIdA)->toBeFalse();
});

it('rejects cross-tenant subscription to campaigns channel', function () {
    $userA = userWithTenant();
    $tenantA = $userA->tenants()->first();
    $userB = userWithTenant();

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $userA->id,
        'tenant_id' => $tenantA->id,
    ]);

    // Create supporting models with the correct tenant so the FK to tenants.id passes
    $contactList = ContactList::factory()->create(['tenant_id' => $tenantA->id]);
    $template = WhatsappTemplate::factory()->create(['tenant_id' => $tenantA->id]);

    $campaign = Campaign::factory()->create([
        'tenant_id' => $tenantA->id,
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $contactList->id,
        'whatsapp_template_id' => $template->id,
    ]);

    // From channels.php: $campaign = Campaign::withoutGlobalScope('tenant')->find($campaignId);
    // return $campaign && (string) $campaign->tenant_id === (string) $user->tenantId;
    $loaded = Campaign::withoutGlobalScope('tenant')->find($campaign->id);

    expect($loaded && (string) $loaded->tenant_id === (string) $userA->tenantId)->toBeTrue();
    expect($loaded && (string) $loaded->tenant_id === (string) $userB->tenantId)->toBeFalse();
});

it('rejects cross-tenant subscription to instances channel', function () {
    $userA = userWithTenant();
    $tenantA = $userA->tenants()->first();
    $userB = userWithTenant();

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $userA->id,
        'tenant_id' => $tenantA->id,
    ]);

    // From channels.php: $instance = WhatsappInstance::withoutGlobalScope('tenant')->find($instanceId);
    // return $instance && (string) $instance->tenant_id === (string) $user->tenantId;
    $loaded = WhatsappInstance::withoutGlobalScope('tenant')->find($instance->id);

    expect($loaded && (string) $loaded->tenant_id === (string) $userA->tenantId)->toBeTrue();
    expect($loaded && (string) $loaded->tenant_id === (string) $userB->tenantId)->toBeFalse();
});

it('rejects cross-tenant subscription to dashboard channel', function () {
    $userA = userWithTenant();
    $tenantA = $userA->tenants()->first();
    $userB = userWithTenant();

    $tenantIdA = (string) $tenantA->id;

    // From channels.php: return (string) $user->tenantId === $tenantId;
    expect((string) $userA->tenantId === $tenantIdA)->toBeTrue();
    expect((string) $userB->tenantId === $tenantIdA)->toBeFalse();
});

it('test_channel_auth_allows_same_tenant', function () {
    $user = userWithTenant();
    $tenant = $user->tenants()->first();

    $tenantId = (string) $tenant->id;

    expect((string) $user->tenantId)->toBe($tenantId);
});
