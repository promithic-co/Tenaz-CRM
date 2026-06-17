<?php

use App\Ai\Agents\BaseCustomerServiceAgent;
use App\Models\Lead;
use App\Services\AgentService;
use App\Services\FactCheckService;

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
