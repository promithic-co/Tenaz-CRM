<?php

use App\Jobs\ProcessLeadFollowUpJob;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use App\Services\CampaignReplyDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('contact_list_entries has a standalone phone index for inbound reply lookup (SCALE-9)', function () {
    $hasPhoneIndex = collect(Schema::getIndexes('contact_list_entries'))
        ->contains(fn (array $index): bool => $index['columns'] === ['phone']);

    expect($hasPhoneIndex)->toBeTrue();
});

test('detect links lead to active campaign when phone matches contact list entry', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5511999990001',
        'opt_in_status' => 'opted_in',
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990001',
        'campaign_id' => null,
    ]);

    $detector = new CampaignReplyDetector;
    $result = $detector->detect($lead, '5511999990001', $user->tenantId);

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($campaign->id);
    expect($lead->fresh()->campaign_id)->toBe($campaign->id);
});

test('detect returns null when no active campaign matches phone', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    // Completed campaign — not active
    Campaign::factory()->completed()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5511999990002',
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990002',
        'campaign_id' => null,
    ]);

    $detector = new CampaignReplyDetector;
    $result = $detector->detect($lead, '5511999990002', $user->tenantId);

    expect($result)->toBeNull();
    expect($lead->fresh()->campaign_id)->toBeNull();
});

test('detect returns existing campaign when already linked and still active', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $campaign = Campaign::factory()->paused()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990003',
        'campaign_id' => $campaign->id,
    ]);

    $detector = new CampaignReplyDetector;
    $result = $detector->detect($lead, '5511999990003', $user->tenantId);

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($campaign->id);
});

test('detect returns null when phone not in any contact list', function () {
    $user = User::factory()->create();

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511000000000',
        'campaign_id' => null,
    ]);

    $detector = new CampaignReplyDetector;
    $result = $detector->detect($lead, '5511000000000', $user->tenantId);

    expect($result)->toBeNull();
});

test('CheckFollowUpsCommand skips leads with campaign_id', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);

    // Lead with campaign_id — should be skipped
    Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'active',
        'campaign_id' => $campaign->id,
        'is_sandbox' => false,
        'last_interaction_at' => now()->subHour(),
    ]);

    // No jobs should be dispatched
    Queue::fake();

    $this->artisan('credflow:check-followups')->assertExitCode(0);

    Queue::assertNotPushed(ProcessLeadFollowUpJob::class);
});
