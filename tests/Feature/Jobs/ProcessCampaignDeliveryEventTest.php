<?php

use App\Jobs\ProcessCampaignDeliveryEventJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ProcessCampaignDeliveryEventJob marks message as delivered and increments counter', function () {
    $campaign = Campaign::factory()->sending()->create(['total_sent' => 1]);

    $message = CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'gs-test-001',
    ]);

    $job = new ProcessCampaignDeliveryEventJob('gs-test-001', 'delivered');
    $job->handle();

    expect($message->fresh()->status)->toBe('delivered');
    expect($message->fresh()->delivered_at)->not->toBeNull();
    expect($campaign->fresh()->total_delivered)->toBe(1);
});

test('ProcessCampaignDeliveryEventJob marks message as read and increments counter', function () {
    $campaign = Campaign::factory()->sending()->create(['total_delivered' => 1]);

    $message = CampaignMessage::factory()->delivered()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'gs-test-002',
    ]);

    $job = new ProcessCampaignDeliveryEventJob('gs-test-002', 'read');
    $job->handle();

    expect($message->fresh()->status)->toBe('read');
    expect($message->fresh()->read_at)->not->toBeNull();
    expect($campaign->fresh()->total_read)->toBe(1);
});

test('ProcessCampaignDeliveryEventJob skips backwards transitions', function () {
    $campaign = Campaign::factory()->sending()->create();

    $message = CampaignMessage::factory()->delivered()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'gs-test-003',
    ]);

    // Delivered → sent is backwards, should be skipped
    $job = new ProcessCampaignDeliveryEventJob('gs-test-003', 'delivered');
    $job->handle();

    // Status stays as delivered (not re-processed); the derived counter reflects the one
    // already-delivered message and the duplicate event does not inflate it (SCALE-1b).
    expect($message->fresh()->status)->toBe('delivered');
    expect($campaign->fresh()->total_delivered)->toBe(1);
});

test('ProcessCampaignDeliveryEventJob does nothing when message not found', function () {
    $job = new ProcessCampaignDeliveryEventJob('non-existent-id', 'delivered');

    // Should not throw
    expect(fn () => $job->handle())->not->toThrow(Throwable::class);
});

test('ProcessCampaignDeliveryEventJob ignores sent event type', function () {
    $campaign = Campaign::factory()->sending()->create();
    $message = CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'gs-test-004',
    ]);

    $job = new ProcessCampaignDeliveryEventJob('gs-test-004', 'sent');
    $job->handle();

    expect($message->fresh()->status)->toBe('sent');
});

test('ProcessCampaignDeliveryEventJob does not mutate another tenant message when tenant threaded (TEN-2)', function () {
    $campaign = Campaign::factory()->sending()->create(['total_sent' => 1]);
    $message = CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'gs-ten2-001',
    ]);

    // Webhook resolved an owning tenant that does NOT own this message.
    $job = new ProcessCampaignDeliveryEventJob('gs-ten2-001', 'delivered', whatsappInstanceId: 999, tenantId: 'other-tenant');
    $job->handle();

    expect($message->fresh()->status)->toBe('sent');
    expect($campaign->fresh()->total_delivered)->toBe(0);
});

test('ProcessCampaignDeliveryEventJob mutates own-tenant message when tenant threaded (TEN-2)', function () {
    $campaign = Campaign::factory()->sending()->create(['total_sent' => 1]);
    $message = CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'gs-ten2-002',
    ]);

    $job = new ProcessCampaignDeliveryEventJob('gs-ten2-002', 'delivered', whatsappInstanceId: 1, tenantId: (string) $campaign->tenant_id);
    $job->handle();

    expect($message->fresh()->status)->toBe('delivered');
    expect($campaign->fresh()->total_delivered)->toBe(1);
});

test('ProcessCampaignDeliveryEventJob saves Meta delivery error details', function () {
    $campaign = Campaign::factory()->sending()->create();

    $message = CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'gs-test-005',
    ]);

    $job = new ProcessCampaignDeliveryEventJob('gs-test-005', 'failed', [[
        'code' => 131026,
        'error_subcode' => 2494010,
        'title' => 'Message Undeliverable',
        'details' => 'Recipient is not a WhatsApp user',
    ]]);
    $job->handle();

    $fresh = $message->fresh();
    expect($fresh->status)->toBe('failed');
    expect($fresh->error_code)->toBe('131026');
    expect($fresh->error_subcode)->toBe('2494010');
    expect($fresh->error_message)->toBe('Recipient is not a WhatsApp user');
});
