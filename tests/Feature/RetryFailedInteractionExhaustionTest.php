<?php

use App\Jobs\RetryFailedInteractionJob;
use App\Models\Agent;
use App\Models\FailedInteraction;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Services\AgentService;
use App\Services\AlertService;
use Illuminate\Support\Facades\Cache;

/**
 * Plan 0.3 (F14): exercise the real RetryFailedInteractionJob::handle() exhaustion branch
 * (not the tautology mirror in FailedInteractionRetryTest). When the underlying retry keeps
 * failing and the attempt count is spent, the lead must be escalated with a ServiceTicket
 * and an operator alert.
 */
test('handle escalates and opens a ticket when retries are exhausted', function () {
    config(['laboratory.retry.max_attempts' => 3]);

    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'status' => 'qualificado']);

    $failure = FailedInteraction::create([
        'lead_id' => $lead->id,
        'agent_id' => $agent->id,
        'error_tag' => 'server_error',
        'error_source' => 'openai',
        'error_message' => 'HTTP 500',
        'context' => ['original_message' => 'Oi'],
        'status' => 'pending',
        'retry_count' => 3,
    ]);

    $agentService = Mockery::mock(AgentService::class);
    $agentService->shouldReceive('process')->once()->andThrow(new RuntimeException('HTTP 503 service unavailable'));

    $alertService = Mockery::mock(AlertService::class);
    $alertService->shouldReceive('sendAlert')
        ->once()
        ->with('retry_exhausted', Mockery::type('string'), Mockery::type('array'));

    (new RetryFailedInteractionJob($failure))->handle($agentService, $alertService);

    expect($failure->fresh()->status)->toBe('escalated');

    $this->assertDatabaseHas('service_tickets', [
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'reason' => 'problema_tecnico',
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    expect($lead->fresh())
        ->status->toBe('escalado')
        ->followup_status->toBe('inactive');
});

test('handle skips the agent turn when the per-attempt claim is already held (REL-5)', function () {
    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'status' => 'qualificado']);

    $failure = FailedInteraction::create([
        'lead_id' => $lead->id,
        'agent_id' => $agent->id,
        'error_tag' => 'timeout',
        'error_source' => 'openai',
        'error_message' => 'timed out',
        'context' => ['original_message' => 'Oi'],
        'status' => 'pending',
        'retry_count' => 0,
    ]);

    // A concurrent execution of this same attempt already claimed it: the LLM must not re-run.
    Cache::add("retry_failed_interaction:{$failure->id}:0", 1, now()->addMinutes(10));

    $agentService = Mockery::mock(AgentService::class);
    $agentService->shouldNotReceive('process');

    $alertService = Mockery::mock(AlertService::class);
    $alertService->shouldNotReceive('sendAlert');

    (new RetryFailedInteractionJob($failure))->handle($agentService, $alertService);

    expect($failure->fresh()->status)->toBe('pending');
});

test('handle reschedules instead of escalating while attempts remain', function () {
    config(['laboratory.retry.max_attempts' => 3]);

    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'status' => 'qualificado']);

    $failure = FailedInteraction::create([
        'lead_id' => $lead->id,
        'agent_id' => $agent->id,
        'error_tag' => 'timeout',
        'error_source' => 'openai',
        'error_message' => 'timed out',
        'context' => ['original_message' => 'Oi'],
        'status' => 'pending',
        'retry_count' => 0,
    ]);

    $agentService = Mockery::mock(AgentService::class);
    $agentService->shouldReceive('process')->once()->andThrow(new RuntimeException('timeout'));

    $alertService = Mockery::mock(AlertService::class);
    $alertService->shouldNotReceive('sendAlert');

    (new RetryFailedInteractionJob($failure))->handle($agentService, $alertService);

    expect($failure->fresh())
        ->status->toBe('pending')
        ->retry_count->toBe(1)
        ->next_retry_at->not->toBeNull();

    expect($lead->fresh()->status)->toBe('qualificado');
});
