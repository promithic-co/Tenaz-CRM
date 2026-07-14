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

/**
 * Seed real campaign_messages so the derived counters (SCALE-1b) reflect a given
 * sent/failed split. `sent` messages carry sent_at (count toward total_sent); `failed`
 * are separate send-time failures (status=failed, no sent_at) — total_failed / total_sent.
 */
function seedCampaignCounters(Campaign $campaign, int $sent, int $failed): void
{
    if ($sent > 0) {
        CampaignMessage::factory()->sent()->count($sent)->create([
            'campaign_id' => $campaign->id,        ]);
    }

    if ($failed > 0) {
        CampaignMessage::factory()->failed()->count($failed)->create([
            'campaign_id' => $campaign->id,        ]);
    }
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

test('cancel sets status to cancelled with manual cancellation reason', function () {
    $campaign = Campaign::factory()->sending()->create();

    $service = new CampaignService;
    $service->cancel($campaign);

    expect($campaign->fresh()->status)->toBe('cancelled');
    expect($campaign->fresh()->failure_reason)->toContain('manualmente');
});

test('checkAndAutoPause returns false when failure rate below threshold', function () {
    $campaign = Campaign::factory()->sending()->create(['error_threshold_percent' => 10]);
    seedCampaignCounters($campaign, sent: 20, failed: 1); // 5% < 10%

    $service = new CampaignService;

    expect($service->checkAndAutoPause($campaign))->toBeFalse();
    expect($campaign->fresh()->status)->toBe('sending');
});

test('checkAndAutoPause pauses campaign when failure rate exceeds threshold', function () {
    $campaign = Campaign::factory()->sending()->create(['error_threshold_percent' => 10]);
    seedCampaignCounters($campaign, sent: 20, failed: 3); // 15% > 10%

    $service = new CampaignService;

    expect($service->checkAndAutoPause($campaign))->toBeTrue();
    expect($campaign->fresh()->status)->toBe('paused');
});

test('checkAndAutoPause debounces rapid checks within the window (SCALE-1)', function () {
    config(['credflow.campaigns.autopause_debounce_seconds' => 3]);

    $campaign = Campaign::factory()->sending()->create(['error_threshold_percent' => 10]);
    seedCampaignCounters($campaign, sent: 20, failed: 1); // 5% < 10%

    $service = new CampaignService;

    // First call wins the debounce gate, evaluates, and finds the rate below threshold.
    expect($service->checkAndAutoPause($campaign))->toBeFalse();

    // Failures now spike past the threshold, but a second call inside the window is gated
    // out before it can take the row lock — so the campaign keeps sending this cycle.
    seedCampaignCounters($campaign, sent: 0, failed: 9); // now 10/20 = 50%
    expect($service->checkAndAutoPause($campaign))->toBeFalse();
    expect($campaign->fresh()->status)->toBe('sending');

    // Once the window elapses the next check evaluates against the locked row and pauses.
    $this->travel(4)->seconds();
    expect($service->checkAndAutoPause($campaign->fresh()))->toBeTrue();
    expect($campaign->fresh()->status)->toBe('paused');
});

test('checkAndAutoPause still evaluates immediately when debounce is disabled', function () {
    config(['credflow.campaigns.autopause_debounce_seconds' => 0]);

    $campaign = Campaign::factory()->sending()->create(['error_threshold_percent' => 10]);
    seedCampaignCounters($campaign, sent: 20, failed: 3); // 15% > 10%

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

test('checkDailyLimit counts only messages sent today (SCALE-6)', function () {
    $campaign = Campaign::factory()->sending()->create(['daily_limit' => 2]);

    // Yesterday and tomorrow must not count toward today's limit.
    CampaignMessage::factory()->count(5)->create([
        'campaign_id' => $campaign->id,
        'status' => 'sent',
        'sent_at' => today()->subDay()->setTime(23, 59, 59),
    ]);
    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'sent',
        'sent_at' => today()->addDay()->startOfDay(),
    ]);
    // Boundary: start- and end-of-day today DO count.
    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'sent',
        'sent_at' => today()->startOfDay(),
    ]);

    $service = new CampaignService;

    expect($service->checkDailyLimit($campaign))->toBeTrue();

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'sent',
        'sent_at' => today()->endOfDay(),
    ]);

    expect($service->checkDailyLimit($campaign))->toBeFalse();
});
