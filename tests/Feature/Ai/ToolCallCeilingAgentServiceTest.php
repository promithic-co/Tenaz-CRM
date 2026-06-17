<?php

use App\Ai\AgentFactory;
use App\Ai\Agents\BaseCustomerServiceAgent;
use App\Ai\Exceptions\ToolCallCeilingExceededException;
use App\Models\Lead;
use App\Services\AgentService;

/**
 * Plan 0.1 (F14): the per-tool ceiling is enforced at the middleware (F4 fix); this proves the
 * exception it throws is caught inside AgentService::process() and converted to the safe human
 * handoff string end-to-end, instead of leaking the exception to the caller.
 */
test('tool call ceiling exception is caught by AgentService and returns the safe handoff message', function () {
    $lead = Lead::factory()->create(['status' => 'qualificado', 'conversation_id' => null]);

    $agent = Mockery::mock(BaseCustomerServiceAgent::class);
    $agent->shouldReceive('forUser')->with($lead)->andReturnSelf();
    $agent->shouldReceive('prompt')->andThrow(new ToolCallCeilingExceededException(4, 3));

    $factory = Mockery::mock(AgentFactory::class);
    $factory->shouldReceive('make')->with($lead)->andReturn($agent);
    app()->instance(AgentFactory::class, $factory);

    $result = app(AgentService::class)->process($lead, 'Quero saber meu saldo');

    expect($result)->toBe('Estou enfrentando uma dificuldade técnica neste momento. Vou passar seu atendimento para nossa equipe humana que poderá ajudá-lo diretamente.');

    $this->assertDatabaseHas('agent_interaction_events', [
        'lead_id' => $lead->id,
        'event_type' => 'tool_loop_blocked',
        'severity' => 'warning',
    ]);
});
