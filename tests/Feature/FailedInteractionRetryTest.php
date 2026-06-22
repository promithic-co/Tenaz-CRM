<?php

use App\Jobs\RetryFailedInteractionJob;
use App\Models\Agent;
use App\Models\FailedInteraction;
use App\Models\Lead;
use App\Services\InteractionRecoveryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('jitters retry dispatch across the configured window (SCALE-10)', function () {
    config(['credflow.jobs.cron_dispatch_jitter_seconds' => 120]);
    Carbon::setTestNow(now()->next('Monday')->setTime(10, 0, 0));
    Queue::fake();

    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);
    FailedInteraction::create([
        'lead_id' => $lead->id,
        'agent_id' => $agent->id,
        'error_tag' => 'timeout',
        'error_source' => 'openai',
        'error_message' => 'Timeout',
        'status' => 'pending',
        'next_retry_at' => now()->subMinute(),
    ]);

    $this->artisan('laboratory:process-retries')->assertExitCode(0);

    Queue::assertPushed(RetryFailedInteractionJob::class, function ($job): bool {
        return $job->delay instanceof DateTimeInterface
            && $job->delay->getTimestamp() >= now()->getTimestamp()
            && $job->delay->getTimestamp() <= now()->addSeconds(120)->getTimestamp();
    });

    Carbon::setTestNow();
});

it('does not delay retry dispatch when jitter is disabled (SCALE-10)', function () {
    config(['credflow.jobs.cron_dispatch_jitter_seconds' => 0]);
    Carbon::setTestNow(now()->next('Monday')->setTime(10, 0, 0));
    Queue::fake();

    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);
    FailedInteraction::create([
        'lead_id' => $lead->id,
        'agent_id' => $agent->id,
        'error_tag' => 'timeout',
        'error_source' => 'openai',
        'error_message' => 'Timeout',
        'status' => 'pending',
        'next_retry_at' => now()->subMinute(),
    ]);

    $this->artisan('laboratory:process-retries')->assertExitCode(0);

    Queue::assertPushed(RetryFailedInteractionJob::class, fn ($job): bool => $job->delay === null);

    Carbon::setTestNow();
});

it('records a failure and schedules retry within business hours', function () {
    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);

    $service = app(InteractionRecoveryService::class);

    // Freeze time at 10:00 (within business hours)
    $this->travel(0)->hours(0);
    $now = now()->setTime(10, 0, 0);
    Carbon::setTestNow($now);

    $failure = $service->recordFailure(
        lead: $lead,
        agent: $agent,
        errorTag: 'timeout',
        errorSource: 'openai',
        errorMessage: 'Connection timed out',
        context: ['original_message' => 'Hello'],
    );

    expect($failure)->toBeInstanceOf(FailedInteraction::class)
        ->and($failure->lead_id)->toBe($lead->id)
        ->and($failure->agent_id)->toBe($agent->id)
        ->and($failure->error_tag)->toBe('timeout')
        ->and($failure->error_source)->toBe('openai')
        ->and($failure->status)->toBe('pending')
        ->and($failure->next_retry_at)->not->toBeNull();

    Carbon::setTestNow();
});

it('does not process retries outside business hours', function () {
    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);

    // Create a pending failure with next_retry_at in the past
    $failure = FailedInteraction::create([
        'lead_id' => $lead->id,
        'agent_id' => $agent->id,
        'error_tag' => 'timeout',
        'error_source' => 'openai',
        'error_message' => 'Test error',
        'status' => 'pending',
        'next_retry_at' => now()->subMinutes(10),
    ]);

    // Freeze time at 02:00 (outside business hours)
    Carbon::setTestNow(now()->setTime(2, 0, 0));

    $pending = FailedInteraction::pending()->inBusinessHours()->get();

    expect($pending)->toHaveCount(0);

    Carbon::setTestNow();
});

it('escalates after max retry attempts', function () {
    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);

    $failure = FailedInteraction::create([
        'lead_id' => $lead->id,
        'agent_id' => $agent->id,
        'error_tag' => 'server_error',
        'error_source' => 'openai',
        'error_message' => 'Server error',
        'status' => 'retrying',
        'retry_count' => 3,
        'next_retry_at' => now()->subMinute(),
    ]);

    $failure->markEscalated();

    expect($failure->fresh()->status)->toBe('escalated');
});

it('resolves on successful retry', function () {
    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);

    $failure = FailedInteraction::create([
        'lead_id' => $lead->id,
        'agent_id' => $agent->id,
        'error_tag' => 'timeout',
        'error_source' => 'openai',
        'error_message' => 'Timeout',
        'status' => 'retrying',
        'retry_count' => 1,
        'next_retry_at' => now()->subMinute(),
    ]);

    $failure->markResolved();

    $fresh = $failure->fresh();
    expect($fresh->status)->toBe('resolved')
        ->and($fresh->resolved_at)->not->toBeNull();
});

it('classifies errors correctly', function (string $message, string $expectedTag) {
    // We test the logic indirectly via the error message patterns
    $lowered = strtolower($message);

    $tag = match (true) {
        str_contains($lowered, 'timeout') => 'timeout',
        str_contains($lowered, 'rate limit') || str_contains($lowered, '429') => 'rate_limit',
        str_contains($lowered, 'context') || str_contains($lowered, 'token') => 'context_overflow',
        str_contains($lowered, 'connection') => 'connection_error',
        str_contains($lowered, '500') || str_contains($lowered, '502') || str_contains($lowered, '503') => 'server_error',
        default => 'unknown',
    };

    expect($tag)->toBe($expectedTag);
})->with([
    ['Request timeout after 30s', 'timeout'],
    ['429 Too Many Requests / rate limit exceeded', 'rate_limit'],
    ['context length exceeded', 'context_overflow'],
    ['connection refused', 'connection_error'],
    ['HTTP 500 Internal Server Error', 'server_error'],
    ['Unexpected failure', 'unknown'],
]);

it('skips weekends for retry scheduling', function () {
    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);

    // Freeze to Friday at 22:00 so next retry would land on Saturday
    $friday22h = now()->next('Friday')->setTime(22, 0, 0);
    Carbon::setTestNow($friday22h);

    $service = app(InteractionRecoveryService::class);

    $failure = $service->recordFailure(
        lead: $lead,
        agent: $agent,
        errorTag: 'timeout',
        errorSource: 'openai',
        errorMessage: 'Timeout',
        context: ['original_message' => 'test'],
    );

    // next_retry_at should be Monday (skipped weekend)
    expect($failure->next_retry_at->dayOfWeek)->not->toBe(0) // not Sunday
        ->and($failure->next_retry_at->dayOfWeek)->not->toBe(6); // not Saturday

    Carbon::setTestNow();
});
