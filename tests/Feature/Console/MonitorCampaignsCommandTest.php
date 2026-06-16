<?php

use App\Models\Campaign;
use App\Models\CampaignMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
