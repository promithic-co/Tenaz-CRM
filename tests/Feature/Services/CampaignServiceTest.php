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
        WhatsappTemplate::factory()->create([
            'tenant_id' => $campaign->tenant_id,
            'whatsapp_instance_id' => $campaign->whatsapp_instance_id,
            'status' => 'APPROVED',
            'meta_template_name' => 'campaign_service_template',
            'meta_waba_id' => $campaign->whatsappInstance->meta_waba_id,
        ])
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

    $service = app(CampaignService::class);
    $service->start($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('sending');
    expect($campaign->started_at)->not->toBeNull();
    expect($campaign->total_recipients)->toBe(3);

    Queue::assertPushed(DispatchCampaignJob::class);
});

test('start throws if campaign is not draft/scheduled', function () {
    $campaign = Campaign::factory()->sending()->create();
    $service = app(CampaignService::class);

    expect(fn () => $service->start($campaign))->toThrow(RuntimeException::class);
});

test('start throws if template is not approved', function () {
    $campaign = Campaign::factory()->create(['status' => 'draft']);
    $campaign->whatsappTemplate()->associate(
        WhatsappTemplate::factory()->pending()->create(['tenant_id' => $campaign->tenant_id])
    );
    $campaign->save();

    $service = app(CampaignService::class);
    expect(fn () => $service->start($campaign))->toThrow(RuntimeException::class);
});

test('pause transitions sending campaign to paused', function () {
    $campaign = Campaign::factory()->sending()->create();

    $service = app(CampaignService::class);
    $service->pause($campaign);

    expect($campaign->fresh()->status)->toBe('paused');
    expect($campaign->fresh()->paused_at)->not->toBeNull();
});

test('pause throws if campaign is not sending', function () {
    $campaign = Campaign::factory()->create(['status' => 'draft']);
    $service = app(CampaignService::class);

    expect(fn () => $service->pause($campaign))->toThrow(RuntimeException::class);
});

test('resume transitions paused campaign to sending and dispatches job', function () {
    Queue::fake();
    $campaign = makeSendableCampaign();
    $campaign->update([
        'status' => 'paused',
        'started_at' => now()->subHour(),
        'paused_at' => now(),
    ]);

    $service = app(CampaignService::class);
    $service->resume($campaign);

    expect($campaign->fresh()->status)->toBe('sending');
    expect($campaign->fresh()->paused_at)->toBeNull();

    Queue::assertPushed(DispatchCampaignJob::class);
});

test('cancel sets status to cancelled with manual cancellation reason', function () {
    $campaign = Campaign::factory()->sending()->create();

    $service = app(CampaignService::class);
    $service->cancel($campaign);

    expect($campaign->fresh()->status)->toBe('cancelled');
    expect($campaign->fresh()->failure_reason)->toContain('manualmente');
});

test('checkAndAutoPause returns false when failure rate below threshold', function () {
    $campaign = Campaign::factory()->sending()->create(['error_threshold_percent' => 10]);
    seedCampaignCounters($campaign, sent: 20, failed: 1); // 5% < 10%

    $service = app(CampaignService::class);

    expect($service->checkAndAutoPause($campaign))->toBeFalse();
    expect($campaign->fresh()->status)->toBe('sending');
});

test('checkAndAutoPause pauses campaign when failure rate exceeds threshold', function () {
    $campaign = Campaign::factory()->sending()->create(['error_threshold_percent' => 10]);
    seedCampaignCounters($campaign, sent: 20, failed: 3); // 15% > 10%

    $service = app(CampaignService::class);

    expect($service->checkAndAutoPause($campaign))->toBeTrue();
    expect($campaign->fresh()->status)->toBe('paused');
});

test('checkAndAutoPause debounces rapid checks within the window (SCALE-1)', function () {
    config(['credflow.campaigns.autopause_debounce_seconds' => 3]);

    $campaign = Campaign::factory()->sending()->create(['error_threshold_percent' => 10]);
    seedCampaignCounters($campaign, sent: 20, failed: 1); // 5% < 10%

    $service = app(CampaignService::class);

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

    $service = app(CampaignService::class);

    expect($service->checkAndAutoPause($campaign))->toBeTrue();
    expect($campaign->fresh()->status)->toBe('paused');
});

test('checkDailyLimit returns true when under limit', function () {
    $campaign = Campaign::factory()->sending()->create(['daily_limit' => 1000]);

    $service = app(CampaignService::class);

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

    $service = app(CampaignService::class);

    expect($service->checkDailyLimit($campaign))->toBeFalse();
});

test('reprocessFailures revives never-sent failed rows and reopens a paused campaign', function () {
    Queue::fake();
    $campaign = Campaign::factory()->paused()->create();

    $failed = CampaignMessage::factory()->failed()->count(2)->create(['campaign_id' => $campaign->id]);

    $service = app(CampaignService::class);
    $revived = $service->reprocessFailures($campaign);

    expect($revived)->toBe(2);
    expect($campaign->fresh()->status)->toBe('sending');
    foreach ($failed as $message) {
        $fresh = $message->fresh();
        expect($fresh->status)->toBe('pending');
        expect($fresh->error_code)->toBeNull();
        expect($fresh->failed_at)->toBeNull();
    }
    Queue::assertPushed(DispatchCampaignJob::class);
});

test('reprocessFailures never re-sends a failed row that already carries sent_at (webhook delivery failure)', function () {
    Queue::fake();
    $campaign = Campaign::factory()->paused()->create();

    // Post-delivery webhook failure: status failed BUT the send already reached the contact.
    $postSendFailure = CampaignMessage::factory()->failed()->create([
        'campaign_id' => $campaign->id,
        'sent_at' => now()->subMinutes(10),
    ]);

    $service = app(CampaignService::class);
    $revived = $service->reprocessFailures($campaign);

    expect($revived)->toBe(0);
    expect($postSendFailure->fresh()->status)->toBe('failed');
    expect($campaign->fresh()->status)->toBe('paused');
    Queue::assertNotPushed(DispatchCampaignJob::class);
});

test('reprocessFailures leaves in_doubt rows untouched', function () {
    Queue::fake();
    $campaign = Campaign::factory()->paused()->create();

    $inDoubt = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'in_doubt',
        'error_code' => 'IN_DOUBT',
    ]);

    $revived = app(CampaignService::class)->reprocessFailures($campaign);

    expect($revived)->toBe(0);
    expect($inDoubt->fresh()->status)->toBe('in_doubt');
});

test('retryMessage revives a single failed row and dispatches', function () {
    Queue::fake();
    $campaign = Campaign::factory()->sending()->create();

    $failed = CampaignMessage::factory()->failed()->create(['campaign_id' => $campaign->id]);
    $otherFailed = CampaignMessage::factory()->failed()->create(['campaign_id' => $campaign->id]);

    $retried = app(CampaignService::class)->retryMessage($campaign, $failed);

    expect($retried)->toBeTrue();
    expect($failed->fresh()->status)->toBe('pending');
    expect($otherFailed->fresh()->status)->toBe('failed');
    Queue::assertPushed(DispatchCampaignJob::class);
});

test('removeRecipients marks pending and queued rows as skipped but spares provider-attempted rows', function () {
    $campaign = Campaign::factory()->sending()->create();

    $pending = CampaignMessage::factory()->create(['campaign_id' => $campaign->id, 'status' => 'pending']);
    $queued = CampaignMessage::factory()->create(['campaign_id' => $campaign->id, 'status' => 'queued']);
    $inFlight = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'queued',
        'provider_attempted_at' => now(),
    ]);

    $removed = app(CampaignService::class)->removeRecipients($campaign, [
        $pending->id,
        $queued->id,
        $inFlight->id,
    ]);

    expect($removed)->toBe(2);
    expect($pending->fresh()->status)->toBe('skipped');
    expect($pending->fresh()->error_code)->toBe('REMOVED_MANUAL');
    expect($queued->fresh()->status)->toBe('skipped');
    expect($inFlight->fresh()->status)->toBe('queued');
});

test('duplicate creates a fresh draft copy with the same config and no history', function () {
    $campaign = Campaign::factory()->completed()->create([
        'daily_limit' => 500,
        'delay_between_ms' => 1500,
        'error_threshold_percent' => 15,
    ]);
    CampaignMessage::factory()->sent()->count(3)->create(['campaign_id' => $campaign->id]);

    $copy = app(CampaignService::class)->duplicate($campaign);

    expect($copy->id)->not->toBe($campaign->id);
    expect($copy->status)->toBe('draft');
    expect($copy->contact_list_id)->toBe($campaign->contact_list_id);
    expect($copy->whatsapp_template_id)->toBe($campaign->whatsapp_template_id);
    expect($copy->daily_limit)->toBe(500);
    expect($copy->delay_between_ms)->toBe(1500);
    expect($copy->error_threshold_percent)->toBe(15);
    expect($copy->name)->toContain('(cópia)');
    expect($copy->messages()->count())->toBe(0);
});

test('updateThrottle updates limits on a paused campaign but rejects a completed one', function () {
    $paused = Campaign::factory()->paused()->create(['daily_limit' => 100]);

    app(CampaignService::class)->updateThrottle($paused, [
        'daily_limit' => 2500,
        'delay_between_ms' => 800,
        'error_threshold_percent' => 20,
    ]);

    expect($paused->fresh()->daily_limit)->toBe(2500);
    expect($paused->fresh()->delay_between_ms)->toBe(800);

    $completed = Campaign::factory()->completed()->create();

    expect(fn () => app(CampaignService::class)->updateThrottle($completed, [
        'daily_limit' => 10,
        'delay_between_ms' => 0,
        'error_threshold_percent' => 5,
    ]))->toThrow(RuntimeException::class);
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

    $service = app(CampaignService::class);

    expect($service->checkDailyLimit($campaign))->toBeTrue();

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'sent',
        'sent_at' => today()->endOfDay(),
    ]);

    expect($service->checkDailyLimit($campaign))->toBeFalse();
});
