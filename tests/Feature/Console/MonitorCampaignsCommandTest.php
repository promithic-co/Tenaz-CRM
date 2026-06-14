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
