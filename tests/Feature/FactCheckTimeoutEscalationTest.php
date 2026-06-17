<?php

use App\Ai\Agents\BaseCustomerServiceAgent;
use App\Models\Lead;
use App\Services\AgentService;
use App\Services\FactCheckService;
use Laravel\Ai\Responses\AgentResponse;

test('fact-check escalates to human on timeout instead of returning unvalidated text', function () {
    $factCheck = Mockery::mock(FactCheckService::class);
    $factCheck->shouldReceive('validateAgentResponse')->andReturn('valor divergente do credito_json');
    app()->instance(FactCheckService::class, $factCheck);

    $service = app(AgentService::class);

    $startTime = (new ReflectionClass($service))->getProperty('requestStartTime');
    $startTime->setAccessible(true);
    $startTime->setValue($service, microtime(true) - 9999);

    $lead = Lead::factory()->create(['status' => 'qualificado']);

    $guardrail = (new ReflectionClass($service))->getMethod('applyFactCheckGuardrail');
    $guardrail->setAccessible(true);

    $agent = Mockery::mock(BaseCustomerServiceAgent::class);

    $result = $guardrail->invoke($service, $agent, $lead, 'Você tem R$ 5000 livres', 'int-timeout-1');

    expect($result)->toBe(AgentService::HUMAN_HANDOFF_MESSAGE)
        ->and($lead->fresh()->status)->toBe('escalado');
});

/** Helper: build an AgentService with requestStartTime pinned to "now" (within budget). */
function factCheckServiceWithinBudget(): AgentService
{
    $service = app(AgentService::class);
    $startTime = (new ReflectionClass($service))->getProperty('requestStartTime');
    $startTime->setAccessible(true);
    $startTime->setValue($service, microtime(true));

    return $service;
}

/** Invoke the private applyFactCheckGuardrail guardrail. */
function invokeGuardrail(AgentService $service, BaseCustomerServiceAgent $agent, Lead $lead, string $text, string $interactionId): string
{
    $guardrail = (new ReflectionClass($service))->getMethod('applyFactCheckGuardrail');
    $guardrail->setAccessible(true);

    return $guardrail->invoke($service, $agent, $lead, $text, $interactionId);
}

test('fact-check passes through unchanged when the response is valid', function () {
    $factCheck = Mockery::mock(FactCheckService::class);
    $factCheck->shouldReceive('validateAgentResponse')->once()->andReturn(null);
    app()->instance(FactCheckService::class, $factCheck);

    $service = factCheckServiceWithinBudget();
    $lead = Lead::factory()->create(['status' => 'qualificado']);
    $agent = Mockery::mock(BaseCustomerServiceAgent::class);

    $result = invokeGuardrail($service, $agent, $lead, 'Tudo certo com seus valores', 'int-pass-1');

    expect($result)->toBe('Tudo certo com seus valores')
        ->and($lead->fresh()->status)->toBe('qualificado');
});

test('fact-check retries within budget and returns the corrected text when the retry passes', function () {
    $factCheck = Mockery::mock(FactCheckService::class);
    $factCheck->shouldReceive('validateAgentResponse')->andReturn('valor divergente', null);
    app()->instance(FactCheckService::class, $factCheck);

    $service = factCheckServiceWithinBudget();
    $lead = Lead::factory()->create(['status' => 'qualificado', 'conversation_id' => 'conv-1']);

    $corrected = Mockery::mock(AgentResponse::class);
    $corrected->shouldReceive('__toString')->andReturn('Valores corrigidos conforme seu benefício');
    $agent = Mockery::mock(BaseCustomerServiceAgent::class);
    $agent->shouldReceive('continue')->once()->andReturnSelf();
    $agent->shouldReceive('prompt')->once()->andReturn($corrected);

    $result = invokeGuardrail($service, $agent, $lead, 'Você tem R$ 9999 livres', 'int-retry-1');

    expect($result)->toBe('Valores corrigidos conforme seu benefício')
        ->and($lead->fresh()->status)->toBe('qualificado');
});

test('fact-check escalates to human when the retry also fails within budget', function () {
    $factCheck = Mockery::mock(FactCheckService::class);
    $factCheck->shouldReceive('validateAgentResponse')->andReturn('valor divergente', 'ainda divergente');
    app()->instance(FactCheckService::class, $factCheck);

    $service = factCheckServiceWithinBudget();
    $lead = Lead::factory()->create(['status' => 'qualificado', 'conversation_id' => 'conv-2']);

    $stillWrong = Mockery::mock(AgentResponse::class);
    $stillWrong->shouldReceive('__toString')->andReturn('Você tem R$ 8888 livres');
    $agent = Mockery::mock(BaseCustomerServiceAgent::class);
    $agent->shouldReceive('continue')->once()->andReturnSelf();
    $agent->shouldReceive('prompt')->once()->andReturn($stillWrong);

    $result = invokeGuardrail($service, $agent, $lead, 'Você tem R$ 9999 livres', 'int-retry-2');

    expect($result)->toBe(AgentService::HUMAN_HANDOFF_MESSAGE)
        ->and($lead->fresh()->status)->toBe('escalado');
});
