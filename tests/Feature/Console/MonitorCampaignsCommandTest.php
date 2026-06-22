<?php

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('monitor-campaigns finds stuck campaigns with a single grouped query (PERF-11 / SCALE-13)', function () {
    Carbon::setTestNow(now()->setTime(10, 0, 0)); // away from the 20:00 daily summary

    $stuck = Campaign::factory()->sending()->create(['total_sent' => 0]);
    $healthy = Campaign::factory()->sending()->create(['total_sent' => 0]);
    $alsoSending = Campaign::factory()->sending()->create(['total_sent' => 0]);

    $old = CampaignMessage::factory()->create(['campaign_id' => $stuck->id, 'status' => 'queued']);
    $old->forceFill(['created_at' => now()->subHours(2)])->saveQuietly();
    CampaignMessage::factory()->create(['campaign_id' => $healthy->id, 'status' => 'queued']);
    CampaignMessage::factory()->create(['campaign_id' => $alsoSending->id, 'status' => 'queued']);

    $alerts = Mockery::spy(AlertService::class);
    app()->instance(AlertService::class, $alerts);

    DB::enableQueryLog();
    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();
    $stuckQueries = collect(DB::getQueryLog())->filter(
        fn ($q): bool => str_contains($q['query'], 'campaign_messages')
            && str_contains(strtolower($q['query']), 'group by')
    );
    DB::disableQueryLog();

    // One grouped query for all three sending campaigns, not a max(created_at) per campaign.
    expect($stuckQueries)->toHaveCount(1);

    $alerts->shouldHaveReceived('sendAlert')
        ->with('stuck_campaign', Mockery::any(), Mockery::on(fn ($ctx): bool => $ctx['campaign_id'] === $stuck->id))
        ->once();

    Carbon::setTestNow();
});

test('monitor-campaigns auto-pauses a sending campaign with a wallet error 1003', function () {
    $campaign = Campaign::factory()->sending()->create();

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'failed',
        'error_code' => '1003',
        'failed_at' => now(),
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('paused');
    expect($campaign->fresh()->paused_at)->not->toBeNull();
});

test('monitor-campaigns pauses on a wallet error that failed long after the row was created', function () {
    $campaign = Campaign::factory()->sending()->create();

    // Row created 30 min ago (e.g. a large/slow campaign) but the 1003 error only
    // landed just now via webhook. Filtering on created_at would miss this.
    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'failed',
        'error_code' => '1003',
        'failed_at' => now(),
    ]);
    $message->forceFill(['created_at' => now()->subMinutes(30)])->saveQuietly();

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('paused');
});

test('monitor-campaigns runs the failure-rate check on a sending campaign that has sent messages', function () {
    // Regression: the failure-rate query used whereColumn('total_sent', '>', 'total_recipients * 0'),
    // which Postgres treats as a (non-existent) column identifier and rejects, making the whole
    // command exit 1 every run. A sending campaign with total_sent > 0 exercises that path.
    Campaign::factory()->sending()->create([
        'total_recipients' => 100,
        'total_sent' => 50,
        'total_failed' => 40,
        'error_threshold_percent' => 10,
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();
});

test('monitor-campaigns backstop auto-pauses a sending campaign over its failure threshold (SCALE-1)', function () {
    // No wallet error — the campaign is simply over its own failure threshold. The hot send
    // path debounces its auto-pause checks, so the monitor must catch this within one cycle.
    $campaign = Campaign::factory()->sending()->create([
        'total_recipients' => 100,
        'total_sent' => 100,
        'total_failed' => 40,
        'error_threshold_percent' => 10,
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('paused');
});

test('monitor-campaigns leaves a sending campaign under its failure threshold sending', function () {
    $campaign = Campaign::factory()->sending()->create([
        'total_recipients' => 100,
        'total_sent' => 100,
        'total_failed' => 5,
        'error_threshold_percent' => 10,
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('sending');
});

test('monitor-campaigns leaves campaigns without a wallet error untouched', function () {
    $campaign = Campaign::factory()->sending()->create();

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'failed',
        'error_code' => 'EXCEPTION',
        'failed_at' => now(),
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('sending');
});
