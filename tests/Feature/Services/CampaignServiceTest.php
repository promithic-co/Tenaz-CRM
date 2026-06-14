<?php

use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactListEntry;
use App\Models\WhatsappTemplate;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function makeSendableCampaign(): Campaign
{
    $campaign = Campaign::factory()->create([
        'status' => 'draft',
        'error_threshold_percent' => 10,
    ]);

    // Ensure template is approved
    $campaign->whatsappTemplate()->associate(
        WhatsappTemplate::factory()->create(['tenant_id' => $campaign->tenant_id, 'status' => 'APPROVED'])
    );
    $campaign->save();

    return $campaign;
}

test('start transitions campaign to sending and dispatches job', function () {
    Queue::fake();
    $campaign = makeSendableCampaign();

    // Create opted-in entries
    ContactListEntry::factory()->count(3)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    $service = new CampaignService;
    $service->start($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('sending');
    expect($campaign->started_at)->not->toBeNull();
    expect($campaign->total_recipients)->toBe(3);

    Queue::assertPushed(DispatchCampaignJob::class);
});

test('start throws if campaign is not draft/scheduled', function () {
    $campaign = Campaign::factory()->sending()->create();
    $service = new CampaignService;

    expect(fn () => $service->start($campaign))->toThrow(RuntimeException::class);
});

test('start throws if template is not approved', function () {
    $campaign = Campaign::factory()->create(['status' => 'draft']);
    $campaign->whatsappTemplate()->associate(
        WhatsappTemplate::factory()->pending()->create(['tenant_id' => $campaign->tenant_id])
    );
    $campaign->save();

    $service = new CampaignService;
    expect(fn () => $service->start($campaign))->toThrow(RuntimeException::class);
});

test('pause transitions sending campaign to paused', function () {
    $campaign = Campaign::factory()->sending()->create();

    $service = new CampaignService;
    $service->pause($campaign);

    expect($campaign->fresh()->status)->toBe('paused');
    expect($campaign->fresh()->paused_at)->not->toBeNull();
});

test('pause throws if campaign is not sending', function () {
    $campaign = Campaign::factory()->create(['status' => 'draft']);
    $service = new CampaignService;

    expect(fn () => $service->pause($campaign))->toThrow(RuntimeException::class);
});

test('resume transitions paused campaign to sending and dispatches job', function () {
    Queue::fake();
    $campaign = Campaign::factory()->paused()->create();

    $service = new CampaignService;
    $service->resume($campaign);

    expect($campaign->fresh()->status)->toBe('sending');
    expect($campaign->fresh()->paused_at)->toBeNull();

    Queue::assertPushed(DispatchCampaignJob::class);
});

test('cancel sets status to failed with manual cancellation reason', function () {
    $campaign = Campaign::factory()->sending()->create();

    $service = new CampaignService;
    $service->cancel($campaign);

    expect($campaign->fresh()->status)->toBe('failed');
    expect($campaign->fresh()->failure_reason)->toContain('manualmente');
});

test('checkAndAutoPause returns false when failure rate below threshold', function () {
    $campaign = Campaign::factory()->sending()->create([
        'total_sent' => 100,
        'total_failed' => 5,
        'error_threshold_percent' => 10,
    ]);

    $service = new CampaignService;

    expect($service->checkAndAutoPause($campaign))->toBeFalse();
    expect($campaign->fresh()->status)->toBe('sending');
});

test('checkAndAutoPause pauses campaign when failure rate exceeds threshold', function () {
    $campaign = Campaign::factory()->sending()->create([
        'total_sent' => 100,
        'total_failed' => 15,
        'error_threshold_percent' => 10,
    ]);

    $service = new CampaignService;

    expect($service->checkAndAutoPause($campaign))->toBeTrue();
    expect($campaign->fresh()->status)->toBe('paused');
});

test('checkDailyLimit returns true when under limit', function () {
    $campaign = Campaign::factory()->sending()->create(['daily_limit' => 1000]);

    $service = new CampaignService;

    expect($service->checkDailyLimit($campaign))->toBeTrue();
});

test('checkDailyLimit returns false when at daily limit', function () {
    $campaign = Campaign::factory()->sending()->create(['daily_limit' => 2]);

    // Create 2 sent messages today
    CampaignMessage::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $service = new CampaignService;

    expect($service->checkDailyLimit($campaign))->toBeFalse();
});
