<?php

use App\Models\Agent;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LegacyTenantKeyRealignmentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rewrites legacy tenant_id from user id string to owner tenant id', function () {
    // Offset tenant auto-increment so user_id != tenant_id
    Tenant::create(['name' => 'offset']);

    $user = User::factory()->create();
    $tenant = $user->tenants()->first();

    expect((string) $user->id)->not->toBe((string) $tenant->id);

    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => (string) $user->id,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create();

    expect((string) $agent->tenant_id)->toBe((string) $user->id);

    app(LegacyTenantKeyRealignmentService::class)->realign();

    $lead->refresh();
    $agent->refresh();

    expect((string) $lead->tenant_id)->toBe((string) $tenant->id);
    expect((string) $agent->tenant_id)->toBe((string) $tenant->id);
});

it('rewrites legacy tenant_id on campaign-family tables (contact_lists, campaigns, etc.)', function () {
    $campaignTenantIntegrityMigration = legacyRealignmentCampaignTenantIntegrityMigration();
    $campaignTenantIntegrityMigration->down();

    try {
        Tenant::create(['name' => 'offset']);

        $user = User::factory()->create();
        $tenant = $user->tenants()->first();

        $contactList = ContactList::factory()->create(['tenant_id' => (string) $user->id]);

        $campaign = Campaign::factory()->create([
            'tenant_id' => (string) $user->id,
            'contact_list_id' => $contactList->id,
        ]);

        expect((string) $contactList->tenant_id)->toBe((string) $user->id);
        expect((string) $campaign->tenant_id)->toBe((string) $user->id);

        app(LegacyTenantKeyRealignmentService::class)->realign(onlyUserId: $user->id);

        $contactList->refresh();
        $campaign->refresh();

        expect((string) $contactList->tenant_id)->toBe((string) $tenant->id);
        expect((string) $campaign->tenant_id)->toBe((string) $tenant->id);
    } finally {
        $campaignTenantIntegrityMigration->up();
    }

    expect((string) $contactList->refresh()->tenant_id)->toBe((string) $tenant->id);
    expect((string) $campaign->refresh()->tenant_id)->toBe((string) $tenant->id);
});

it('is idempotent when data is already aligned', function () {
    $user = User::factory()->create();
    $tenant = $user->tenants()->first();

    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => (string) $tenant->id,
    ]);

    Lead::factory()->forTenant((string) $tenant->id)->create(['agent_id' => $agent->id]);

    $first = app(LegacyTenantKeyRealignmentService::class)->realign();
    $second = app(LegacyTenantKeyRealignmentService::class)->realign();

    expect($first['rows_updated'])->toBe(0);
    expect($second['rows_updated'])->toBe(0);
});

function legacyRealignmentCampaignTenantIntegrityMigration(): Migration
{
    return require database_path('migrations/2026_07_15_010008_enforce_campaign_tenant_reference_integrity.php');
}
