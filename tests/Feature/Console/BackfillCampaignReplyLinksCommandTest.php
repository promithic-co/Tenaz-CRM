<?php

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a reply left unlinked by the exact-match detector is attributed to its campaign', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $campaign = Campaign::factory()->paused()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    // Imported with the 9th digit, replied from the form without it — the pair the exact
    // match could never reconcile.
    ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5567998601348',
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '556798601348',
        'campaign_id' => null,
        'last_inbound_at' => now()->subDay(),
    ]);

    $this->artisan('campaigns:backfill-reply-links')->assertExitCode(0);

    expect($lead->fresh()->campaign_id)->toBe($campaign->id);
});

test('a completed campaign still gets its replies back', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $campaign = Campaign::factory()->completed()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => '5511999990001']);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990001',
        'campaign_id' => null,
        'last_inbound_at' => now()->subWeek(),
    ]);

    // The live detector deliberately ignores finished campaigns; the repair must not, or
    // every campaign that ever ended keeps reporting zero replies forever.
    $this->artisan('campaigns:backfill-reply-links')->assertExitCode(0);

    expect($lead->fresh()->campaign_id)->toBe($campaign->id);
});

test('a recipient who never replied is left unlinked', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    Campaign::factory()->paused()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => '5511999990002']);

    // A campaign send creates a lead per recipient. Linking one that never answered is
    // exactly how the replied counter would start reporting the whole list.
    $silent = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990002',
        'campaign_id' => null,
        'last_inbound_at' => null,
    ]);

    $this->artisan('campaigns:backfill-reply-links')->assertExitCode(0);

    expect($silent->fresh()->campaign_id)->toBeNull();
});

test('an existing link is never overwritten', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $newer = Campaign::factory()->paused()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);
    $older = Campaign::factory()->completed()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => '5511999990003']);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990003',
        'campaign_id' => $older->id,
        'last_inbound_at' => now(),
    ]);

    $this->artisan('campaigns:backfill-reply-links')->assertExitCode(0);

    expect($lead->fresh()->campaign_id)->toBe($older->id)
        ->and($lead->fresh()->campaign_id)->not->toBe($newer->id);
});

test('another tenant campaign is never attributed', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $theirList = ContactList::factory()->create(['tenant_id' => $other->tenantId]);
    Campaign::factory()->paused()->create([
        'tenant_id' => $other->tenantId,
        'contact_list_id' => $theirList->id,
    ]);
    ContactListEntry::factory()->create(['contact_list_id' => $theirList->id, 'phone' => '5511999990004']);

    $ourLead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990004',
        'campaign_id' => null,
        'last_inbound_at' => now(),
    ]);

    $this->artisan('campaigns:backfill-reply-links')->assertExitCode(0);

    expect($ourLead->fresh()->campaign_id)->toBeNull();
});

test('the backfill writes nothing on a dry run', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    Campaign::factory()->paused()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => '5511999990005']);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990005',
        'campaign_id' => null,
        'last_inbound_at' => now(),
    ]);

    $this->artisan('campaigns:backfill-reply-links', ['--dry-run' => true])->assertExitCode(0);

    expect($lead->fresh()->campaign_id)->toBeNull();
});

test('the backfill is idempotent', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    Campaign::factory()->paused()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => '5511999990006']);
    Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990006',
        'campaign_id' => null,
        'last_inbound_at' => now(),
    ]);

    $this->artisan('campaigns:backfill-reply-links')->assertExitCode(0);
    $this->artisan('campaigns:backfill-reply-links')
        ->assertExitCode(0)
        ->expectsOutputToContain('No unlinked replies to repair.');
});
