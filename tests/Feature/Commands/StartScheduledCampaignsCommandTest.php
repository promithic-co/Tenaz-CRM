<?php

use App\Models\Campaign;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('command starts campaigns that are scheduled and due', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create([
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'error_threshold_percent' => 10,
    ]);
    $campaign->whatsappTemplate()->associate(
        WhatsappTemplate::factory()->create(['tenant_id' => $campaign->tenant_id, 'status' => 'APPROVED'])
    );
    $campaign->save();

    $this->artisan('credflow:start-scheduled-campaigns')
        ->assertExitCode(0);

    expect($campaign->fresh()->status)->toBe('sending');
});

test('command skips campaigns not yet due', function () {
    Queue::fake();

    Campaign::factory()->scheduled()->create();

    $this->artisan('credflow:start-scheduled-campaigns')
        ->assertExitCode(0);

    // scheduled() factory sets scheduled_at to now()->addHour() so nothing starts
    expect(Campaign::where('status', 'sending')->count())->toBe(0);
});

test('command does nothing when no scheduled campaigns exist', function () {
    $this->artisan('credflow:start-scheduled-campaigns')
        ->assertExitCode(0);
});

test('command continues on individual campaign failure', function () {
    Queue::fake();

    // Campaign with unapproved template will fail
    $bad = Campaign::factory()->create([
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
    ]);
    $bad->whatsappTemplate()->associate(
        WhatsappTemplate::factory()->pending()->create(['tenant_id' => $bad->tenant_id])
    );
    $bad->save();

    // Campaign with approved template should succeed
    $good = Campaign::factory()->create([
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'error_threshold_percent' => 10,
    ]);
    $good->whatsappTemplate()->associate(
        WhatsappTemplate::factory()->create(['tenant_id' => $good->tenant_id, 'status' => 'APPROVED'])
    );
    $good->save();

    $this->artisan('credflow:start-scheduled-campaigns')
        ->assertExitCode(0);

    expect($bad->fresh()->status)->toBe('scheduled');
    expect($good->fresh()->status)->toBe('sending');
});
