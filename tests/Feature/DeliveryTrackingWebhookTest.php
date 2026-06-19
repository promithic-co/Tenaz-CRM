<?php

use App\Jobs\ProcessCampaignDeliveryEventJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('delivery event job marks message delivered and increments counter', function () {
    $campaign = Campaign::factory()->sending()->create(['total_sent' => 1]);

    $message = CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'provider-delivery-001',
    ]);

    $job = new ProcessCampaignDeliveryEventJob('provider-delivery-001', 'delivered');
    $job->handle();

    expect($message->fresh()->status)->toBe('delivered');
    expect($campaign->fresh()->total_delivered)->toBe(1);
});

test('delivery event job marks message read and increments counter', function () {
    $campaign = Campaign::factory()->sending()->create(['total_delivered' => 1]);

    $message = CampaignMessage::factory()->delivered()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'provider-read-001',
    ]);

    $job = new ProcessCampaignDeliveryEventJob('provider-read-001', 'read');
    $job->handle();

    expect($message->fresh()->status)->toBe('read');
    expect($campaign->fresh()->total_read)->toBe(1);
});

test('delivery event job ignores event without message id match', function () {
    $job = new ProcessCampaignDeliveryEventJob('non-existent-provider-id', 'delivered');

    expect(fn () => $job->handle())->not->toThrow(Throwable::class);
});

test('delivery event job skips backwards status transitions', function () {
    $campaign = Campaign::factory()->sending()->create();

    $message = CampaignMessage::factory()->delivered()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'provider-backwards-001',
    ]);

    // delivered → sent is a backwards transition
    $job = new ProcessCampaignDeliveryEventJob('provider-backwards-001', 'delivered');
    $job->handle();

    expect($message->fresh()->status)->toBe('delivered');
    expect($campaign->fresh()->total_delivered)->toBe(0);
});

test('delivery event job dispatched to queue', function () {
    Queue::fake();

    ProcessCampaignDeliveryEventJob::dispatch('provider-queued-001', 'delivered');

    Queue::assertPushed(ProcessCampaignDeliveryEventJob::class, fn ($job) => $job->providerMessageId === 'provider-queued-001'
        && $job->eventType === 'delivered'
    );
});

test('duplicate delivered events increment the counter exactly once', function () {
    $campaign = Campaign::factory()->sending()->create(['total_sent' => 1]);

    $message = CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'provider-dup-001',
    ]);

    (new ProcessCampaignDeliveryEventJob('provider-dup-001', 'delivered'))->handle();
    (new ProcessCampaignDeliveryEventJob('provider-dup-001', 'delivered'))->handle();

    expect($message->fresh()->status)->toBe('delivered')
        ->and($campaign->fresh()->total_delivered)->toBe(1);
});

test('an in_doubt message is resolved and the send is counted when a webhook status arrives', function () {
    $campaign = Campaign::factory()->sending()->create(['total_sent' => 0]);

    // Ambiguous send: no wamid stored, status in_doubt.
    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'in_doubt',
        'provider_message_id' => null,
        'provider_attempted_at' => now(),
    ]);

    // Webhook echoes biz_opaque_callback_data = the campaign_message id.
    (new ProcessCampaignDeliveryEventJob('wamid.resolved.001', 'delivered', [], (string) $message->id))->handle();

    $fresh = $message->fresh();
    expect($fresh->status)->toBe('delivered')
        ->and($fresh->provider_message_id)->toBe('wamid.resolved.001')
        ->and($campaign->fresh()->total_sent)->toBe(1)
        ->and($campaign->fresh()->total_delivered)->toBe(1);
});
