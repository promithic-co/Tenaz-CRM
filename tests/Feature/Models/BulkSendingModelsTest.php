<?php

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// WhatsappTemplate tests
test('whatsapp template approved scope filters correctly', function () {
    $user = User::factory()->create();
    WhatsappTemplate::factory()->state(['tenant_id' => $user->tenantId, 'status' => 'APPROVED'])->create();
    WhatsappTemplate::factory()->state(['tenant_id' => $user->tenantId, 'status' => 'pending'])->create();
    WhatsappTemplate::factory()->state(['tenant_id' => $user->tenantId, 'status' => 'REJECTED'])->create();

    $approved = WhatsappTemplate::forTenant((string) $user->id)->approved()->get();

    expect($approved)->toHaveCount(1);
    expect($approved->first()->status)->toBe('APPROVED');
});

test('whatsapp template isApproved returns correct bool', function () {
    $approved = WhatsappTemplate::factory()->create(['status' => 'APPROVED']);
    $pending = WhatsappTemplate::factory()->create(['status' => 'pending']);

    expect($approved->isApproved())->toBeTrue();
    expect($pending->isApproved())->toBeFalse();
});

test('whatsapp template variableNames returns correct array', function () {
    $template = WhatsappTemplate::factory()->create(['variables_count' => 3]);

    expect($template->variableNames())->toBe(['1', '2', '3']);
});

test('whatsapp template variableNames returns empty array when no variables', function () {
    $template = WhatsappTemplate::factory()->create(['variables_count' => 0]);

    expect($template->variableNames())->toBe([]);
});

// ContactList tests
test('contact list refreshEntriesCount updates count', function () {
    $list = ContactList::factory()->create();
    ContactListEntry::factory()->count(3)->state(['contact_list_id' => $list->id])->create();

    $list->refreshEntriesCount();

    expect($list->fresh()->entries_count)->toBe(3);
});

test('contact list scopeForTenant filters correctly', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ContactList::factory()->state(['tenant_id' => $user1->id])->create();
    ContactList::factory()->state(['tenant_id' => $user2->id])->create();

    $lists = ContactList::forTenant((string) $user1->id)->get();

    expect($lists)->toHaveCount(1);
});

// ContactListEntry tests
test('contact list entry unique constraint prevents duplicate phone per list', function () {
    $list = ContactList::factory()->create();
    ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => '5511999990001']);

    expect(fn () => ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => '5511999990001']))
        ->toThrow(QueryException::class);
});

test('contact list entry markOptedIn sets status and timestamp', function () {
    $entry = ContactListEntry::factory()->pending()->create();

    $entry->markOptedIn();

    expect($entry->fresh()->opt_in_status)->toBe('opted_in');
    expect($entry->fresh()->opt_in_at)->not->toBeNull();
});

test('contact list entry markOptedOut sets status and timestamp', function () {
    $entry = ContactListEntry::factory()->create();

    $entry->markOptedOut();

    expect($entry->fresh()->opt_in_status)->toBe('opted_out');
    expect($entry->fresh()->opt_out_at)->not->toBeNull();
});

test('contact list entry scopeOptedIn filters correctly', function () {
    $list = ContactList::factory()->create();
    ContactListEntry::factory()->state(['contact_list_id' => $list->id])->create(['opt_in_status' => 'opted_in']);
    ContactListEntry::factory()->pending()->state(['contact_list_id' => $list->id])->create();
    ContactListEntry::factory()->optedOut()->state(['contact_list_id' => $list->id])->create();

    $optedIn = ContactListEntry::where('contact_list_id', $list->id)->optedIn()->get();

    expect($optedIn)->toHaveCount(1);
});

// Campaign tests
test('campaign status methods return correct booleans', function () {
    $draft = Campaign::factory()->create(['status' => 'draft']);
    $sending = Campaign::factory()->sending()->create();
    $paused = Campaign::factory()->paused()->create();
    $completed = Campaign::factory()->completed()->create();

    expect($draft->isDraft())->toBeTrue();
    expect($draft->isSending())->toBeFalse();
    expect($sending->isSending())->toBeTrue();
    expect($paused->isPaused())->toBeTrue();
    expect($completed->isCompleted())->toBeTrue();
    expect($draft->canStart())->toBeTrue();
    expect($sending->canPause())->toBeTrue();
    expect($paused->canResume())->toBeTrue();
});

test('campaign rate calculations guard division by zero', function () {
    $campaign = Campaign::factory()->create([
        'total_sent' => 0,
        'total_delivered' => 0,
        'total_read' => 0,
        'total_failed' => 0,
    ]);

    expect($campaign->deliveryRate())->toBe(0.0);
    expect($campaign->readRate())->toBe(0.0);
    expect($campaign->failureRate())->toBe(0.0);
});

test('campaign rate calculations are correct', function () {
    // Counters derive from message state (SCALE-1b): sent=20, delivered=16, read=8, failed=1.
    $campaign = Campaign::factory()->create();
    CampaignMessage::factory()->count(8)->create([
        'campaign_id' => $campaign->id,
        'status' => 'read', 'sent_at' => now(), 'delivered_at' => now(), 'read_at' => now(),
    ]);
    CampaignMessage::factory()->count(8)->create([
        'campaign_id' => $campaign->id,
        'status' => 'delivered', 'sent_at' => now(), 'delivered_at' => now(),
    ]);
    CampaignMessage::factory()->count(4)->create([
        'campaign_id' => $campaign->id,
        'status' => 'sent', 'sent_at' => now(),
    ]);
    CampaignMessage::factory()->failed()->count(1)->create([
        'campaign_id' => $campaign->id,
    ]);

    expect($campaign->deliveryRate())->toBe(80.0); // 16/20
    expect($campaign->readRate())->toBe(50.0);     // 8/16
    expect($campaign->failureRate())->toBe(5.0);   // 1/20
});

test('campaign counters derive live from message rows (SCALE-1b)', function () {
    // The total_* columns are no longer written on the hot path; reads derive from
    // campaign_messages. A status=sent message carries sent_at; a delivery-failed one
    // carries both sent_at and status=failed.
    $campaign = Campaign::factory()->create();
    CampaignMessage::factory()->count(9)->create([
        'campaign_id' => $campaign->id,
        'status' => 'sent', 'sent_at' => now(),
    ]);
    CampaignMessage::factory()->count(1)->create([
        'campaign_id' => $campaign->id,
        'status' => 'failed', 'sent_at' => now(), 'failed_at' => now(),
    ]);

    $fresh = $campaign->fresh();
    expect($fresh->total_sent)->toBe(10)   // all 10 carry sent_at
        ->and($fresh->total_failed)->toBe(1)
        ->and($fresh->total_delivered)->toBe(0);
});

// CampaignMessage tests
test('campaign message unique constraint prevents duplicate entries per campaign', function () {
    $campaign = Campaign::factory()->create();
    $entry = ContactListEntry::factory()->create();
    CampaignMessage::factory()->create(['campaign_id' => $campaign->id, 'contact_list_entry_id' => $entry->id]);

    expect(fn () => CampaignMessage::factory()->create(['campaign_id' => $campaign->id, 'contact_list_entry_id' => $entry->id]))
        ->toThrow(QueryException::class);
});

test('campaign message markSent updates status and provider message id', function () {
    $message = CampaignMessage::factory()->create();

    $message->markSent('provider-msg-123');

    expect($message->fresh()->status)->toBe('sent');
    expect($message->fresh()->provider_message_id)->toBe('provider-msg-123');
    expect($message->fresh()->sent_at)->not->toBeNull();
});

test('campaign message markDelivered updates status', function () {
    $message = CampaignMessage::factory()->sent()->create();

    $message->markDelivered();

    expect($message->fresh()->status)->toBe('delivered');
    expect($message->fresh()->delivered_at)->not->toBeNull();
});

test('campaign message markFailed updates status and error fields', function () {
    $message = CampaignMessage::factory()->create();

    $message->markFailed('1001', 'User not opted in');

    expect($message->fresh()->status)->toBe('failed');
    expect($message->fresh()->error_code)->toBe('1001');
    expect($message->fresh()->error_message)->toBe('User not opted in');
    expect($message->fresh()->failed_at)->not->toBeNull();
});

test('campaign message canTransitionTo respects lifecycle order', function () {
    $message = CampaignMessage::factory()->create(['status' => 'sent']);

    expect($message->canTransitionTo('delivered'))->toBeTrue();
    expect($message->canTransitionTo('pending'))->toBeFalse();
    expect($message->canTransitionTo('failed'))->toBeTrue();
});

// Lead campaign relationship
test('lead belongs to campaign', function () {
    $campaign = Campaign::factory()->create();
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id, 'tenant_id' => $campaign->tenant_id]);

    expect($lead->campaign)->not->toBeNull();
    expect($lead->campaign->id)->toBe($campaign->id);
});
