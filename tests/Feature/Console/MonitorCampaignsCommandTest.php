<?php

use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactListEntry;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

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

    // One grouped query per sweep (completion + revive + stuck), not a per-campaign aggregate.
    expect($stuckQueries)->toHaveCount(3);

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
    // command exit 1 every run. A sending campaign with derived total_sent > 0 exercises that path.
    $campaign = Campaign::factory()->sending()->create(['total_recipients' => 100, 'error_threshold_percent' => 10]);
    CampaignMessage::factory()->sent()->count(20)->create(['campaign_id' => $campaign->id]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();
});

test('monitor-campaigns backstop auto-pauses a sending campaign over its failure threshold (SCALE-1)', function () {
    // No wallet error — the campaign is simply over its own failure threshold. The hot send
    // path debounces its auto-pause checks, so the monitor must catch this within one cycle.
    $campaign = Campaign::factory()->sending()->create(['total_recipients' => 100, 'error_threshold_percent' => 10]);
    // 20 sent, 6 of them failed → 30% failure rate, over the 10% threshold.
    CampaignMessage::factory()->sent()->count(14)->create(['campaign_id' => $campaign->id]);
    CampaignMessage::factory()->sent()->count(6)->create(['campaign_id' => $campaign->id, 'status' => 'failed', 'failed_at' => now()]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('paused');
});

test('monitor-campaigns leaves a sending campaign under its failure threshold sending', function () {
    $campaign = Campaign::factory()->sending()->create(['total_recipients' => 100, 'error_threshold_percent' => 10]);
    // 20 sent, 1 failed → 5% failure rate, under the 10% threshold.
    CampaignMessage::factory()->sent()->count(19)->create(['campaign_id' => $campaign->id]);
    CampaignMessage::factory()->sent()->count(1)->create(['campaign_id' => $campaign->id, 'status' => 'failed', 'failed_at' => now()]);
    // Still-in-flight work keeps the campaign out of the CAMP-03 completion sweep.
    CampaignMessage::factory()->create(['campaign_id' => $campaign->id, 'status' => 'queued']);

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
    // Still-in-flight work keeps the campaign out of the CAMP-03 completion sweep.
    CampaignMessage::factory()->create(['campaign_id' => $campaign->id, 'status' => 'queued']);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('sending');
});

test('monitor-campaigns completes a sending campaign whose work is exhausted (CAMP-03)', function () {
    $campaign = Campaign::factory()->sending()->create(['total_recipients' => 2]);
    $entries = ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    // Mixed terminal states: the old counter predicate (total_sent >= total_recipients)
    // never closed this campaign because the failed row has no sent_at.
    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
    ]);
    CampaignMessage::factory()->failed()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[1]->id,
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('completed')
        ->and($campaign->fresh()->completed_at)->not->toBeNull();
});

test('monitor-campaigns does not complete a campaign with an actionable message row (CAMP-03)', function () {
    Queue::fake();

    foreach (['pending', 'queued'] as $status) {
        $campaign = Campaign::factory()->sending()->create();
        $entries = ContactListEntry::factory()->count(2)->create([
            'contact_list_id' => $campaign->contact_list_id,
            'opt_in_status' => 'opted_in',
        ]);

        CampaignMessage::factory()->sent()->create([
            'campaign_id' => $campaign->id,
            'contact_list_entry_id' => $entries[0]->id,
        ]);
        CampaignMessage::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_list_entry_id' => $entries[1]->id,
            'status' => $status,
        ]);

        $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

        expect($campaign->fresh()->status)->toBe('sending');
    }
});

test('monitor-campaigns does not complete a campaign with a dispatchable entry missing its row (CAMP-03)', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create();
    $entries = ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    // Fan-out crashed mid-run: one entry never got a message row and must still be sent.
    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('sending');
});

test('monitor-campaigns completes when the only entries without rows are opted out (CAMP-03)', function () {
    $campaign = Campaign::factory()->sending()->create();
    $sent = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);
    // Suppressed at fan-out: never gets a row, must not hold the campaign open forever.
    ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_out',
    ]);

    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $sent->id,
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('completed');
});

test('monitor-campaigns treats in_doubt rows as settled for completion (CAMP-03)', function () {
    $campaign = Campaign::factory()->sending()->create();
    $entries = ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
    ]);
    // in_doubt is terminal by design (never re-sent); a late webhook still upgrades it
    // after completion because the counters are derived from the message rows.
    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[1]->id,
        'status' => 'in_doubt',
        'provider_attempted_at' => now(),
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('completed');
});

test('monitor-campaigns skips campaigns with zero message rows to not race the dispatcher (CAMP-03)', function () {
    Queue::fake();

    // Sending but the fan-out has not run yet (e.g. a smart list awaiting materialization):
    // entries and rows are both empty, and completing here would cancel the whole send.
    $campaign = Campaign::factory()->sending()->create();

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('sending');
    // Zero rows also means no revive: the original dispatcher may still be in flight.
    Queue::assertNotPushed(DispatchCampaignJob::class);
});

test('monitor-campaigns revives an idle campaign with parked rows and budget, debounced (CAMP-04)', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create(['daily_limit' => 10]);
    $entries = ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
    ]);
    // Parked by a daily-limit stop or a pause: no live job holds this row.
    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[1]->id,
        'status' => 'pending',
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    Queue::assertPushed(DispatchCampaignJob::class, 1);

    // Debounced: an immediately following sweep must not stack a duplicate dispatcher.
    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    Queue::assertPushed(DispatchCampaignJob::class, 1);
});

test('monitor-campaigns revives a campaign whose budget-stopped fan-out left entries without rows (CAMP-04)', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create(['daily_limit' => 10]);
    $entries = ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    // Yesterday's budget stopped the fan-out before the second entry got a row.
    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    Queue::assertPushed(DispatchCampaignJob::class, 1);
});

test('monitor-campaigns does not revive without budget or with jobs in flight (CAMP-04)', function () {
    Queue::fake();

    // Budget exhausted: today's sends already hit the daily limit.
    $exhausted = Campaign::factory()->sending()->create(['daily_limit' => 1]);
    $exhaustedEntries = ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $exhausted->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);
    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $exhausted->id,
        'contact_list_entry_id' => $exhaustedEntries[0]->id,
    ]);
    CampaignMessage::factory()->create([
        'campaign_id' => $exhausted->id,
        'contact_list_entry_id' => $exhaustedEntries[1]->id,
        'status' => 'pending',
    ]);

    // In flight: a queued row means the dispatcher/send pipeline still owns this campaign.
    $inFlight = Campaign::factory()->sending()->create(['daily_limit' => 10]);
    $inFlightEntries = ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $inFlight->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);
    CampaignMessage::factory()->create([
        'campaign_id' => $inFlight->id,
        'contact_list_entry_id' => $inFlightEntries[0]->id,
        'status' => 'queued',
    ]);
    CampaignMessage::factory()->create([
        'campaign_id' => $inFlight->id,
        'contact_list_entry_id' => $inFlightEntries[1]->id,
        'status' => 'pending',
    ]);

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();

    Queue::assertNotPushed(DispatchCampaignJob::class);
});

test('monitor-campaigns revives on the next day once the budget resets (CAMP-04)', function () {
    Carbon::setTestNow(now()->setTime(10, 0, 0));
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create(['daily_limit' => 1]);
    $entries = ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);
    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
    ]);
    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[1]->id,
        'status' => 'pending',
    ]);

    // Today the budget is spent — nothing to revive.
    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();
    Queue::assertNotPushed(DispatchCampaignJob::class);

    // Tomorrow the sent-today window rolls over and the parked row is picked back up.
    Carbon::setTestNow(now()->addDay()->setTime(10, 0, 0));

    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();
    Queue::assertPushed(DispatchCampaignJob::class, 1);

    Carbon::setTestNow();
});
