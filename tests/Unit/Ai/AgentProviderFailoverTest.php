<?php

use App\Ai\Agents\BaseCustomerServiceAgent;
use App\Ai\Agents\CredFlowAgent;
use App\Models\Lead;

/**
 * Plan 1.3 (F5): provider() must expose a [provider => model] failover chain so laravel/ai's
 * withModelFailover() engages on FailoverableException (rate limit / overload). Off by default,
 * skipped under an explicit override or a no-op same-provider fallback.
 */
function failoverAgent(array $resolvedConfig): CredFlowAgent
{
    $agent = new CredFlowAgent(Lead::factory()->make(['tenant_id' => 'default']));

    $prop = (new ReflectionClass(BaseCustomerServiceAgent::class))->getProperty('resolvedConfig');
    $prop->setAccessible(true);
    $prop->setValue($agent, $resolvedConfig);

    return $agent;
}

test('provider returns a single string when failover is disabled', function () {
    config(['credflow.agent.failover.enabled' => false]);

    $agent = failoverAgent(['agent_provider' => 'openai', 'agent_model' => 'gpt-4o']);

    expect($agent->provider())->toBe('openai');
});

test('provider returns a two-entry failover chain when enabled and configured', function () {
    config([
        'credflow.agent.failover.enabled' => true,
        'credflow.agent.failover.provider' => 'anthropic',
        'credflow.agent.failover.model' => 'claude-haiku-4-5',
    ]);

    $agent = failoverAgent(['agent_provider' => 'openai', 'agent_model' => 'gpt-4o']);

    expect($agent->provider())->toBe([
        'openai' => 'gpt-4o',
        'anthropic' => 'claude-haiku-4-5',
    ]);
});

test('provider ignores failover when an explicit override is set', function () {
    config([
        'credflow.agent.failover.enabled' => true,
        'credflow.agent.failover.provider' => 'anthropic',
        'credflow.agent.failover.model' => 'claude-haiku-4-5',
    ]);

    $agent = failoverAgent(['agent_provider' => 'openai', 'agent_model' => 'gpt-4o']);
    $agent->withModelOverride('groq', 'llama-3.3-70b');

    expect($agent->provider())->toBe('groq');
});

test('provider skips a no-op failover when the fallback matches the primary provider', function () {
    config([
        'credflow.agent.failover.enabled' => true,
        'credflow.agent.failover.provider' => 'openai',
        'credflow.agent.failover.model' => 'gpt-4o-mini',
    ]);

    $agent = failoverAgent(['agent_provider' => 'openai', 'agent_model' => 'gpt-4o']);

    expect($agent->provider())->toBe('openai');
});

test('withModelOverride honors a provider-only override (previous no-op bug)', function () {
    config(['credflow.agent.failover.enabled' => false]);

    $agent = failoverAgent(['agent_provider' => 'openai', 'agent_model' => 'gpt-4o']);
    $agent->withModelOverride('groq', null);

    expect($agent->provider())->toBe('groq');
});
