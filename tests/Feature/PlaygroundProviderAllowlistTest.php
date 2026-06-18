<?php

use App\Ai\Agents\BlindspotScannerAgent;
use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\ScenarioGeneratorAgent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;

uses(RefreshDatabase::class);

/** F8: the LLM-invoking playground endpoints must reject provider/model injection. */
function allowlistSandboxLead(User $user): Lead
{
    return Lead::factory()->sandbox()->create(['tenant_id' => (string) $user->tenantId]);
}

test('chat rejects a model_override outside the playground allow-list', function () {
    $user = userWithTenant();
    $lead = allowlistSandboxLead($user);

    $this->actingAs($user)
        ->postJson(route('playground.chat', $lead), ['message' => 'oi', 'model_override' => 'evil/gpt-9000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('model_override');
});

test('chat accepts an allow-listed model_override', function () {
    $user = userWithTenant();
    $lead = allowlistSandboxLead($user);
    Ai::fakeAgent(CredFlowAgent::class, ['ok']);

    $this->actingAs($user)
        ->postJson(route('playground.chat', $lead), ['message' => 'oi', 'model_override' => 'anthropic/claude-haiku-4-5'])
        ->assertOk();
});

test('scanBlindspots rejects a tester_model outside the allow-list', function () {
    $user = userWithTenant();
    allowlistSandboxLead($user);

    $this->actingAs($user)
        ->postJson(route('playground.scanBlindspots'), ['tester_model' => 'evil/model'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tester_model');
});

test('scanBlindspots accepts an allow-listed tester_model', function () {
    $user = userWithTenant();
    allowlistSandboxLead($user);
    Ai::fakeAgent(BlindspotScannerAgent::class, [json_encode([['category' => 'c', 'scenario' => 's', 'severity' => 'low', 'target' => 't']])]);

    $this->actingAs($user)
        ->postJson(route('playground.scanBlindspots'), ['tester_model' => 'gpt-4o'])
        ->assertOk();
});

test('generateScenario rejects a tester_model outside the allow-list', function () {
    $user = userWithTenant();

    $this->actingAs($user)
        ->postJson(route('playground.generateScenario'), ['objective' => 'x', 'cycle' => 1, 'tester_model' => 'evil/model'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tester_model');
});

test('generateScenario accepts an allow-listed tester_model', function () {
    $user = userWithTenant();
    Ai::fakeAgent(ScenarioGeneratorAgent::class, ['cenário']);

    $this->actingAs($user)
        ->postJson(route('playground.generateScenario'), ['objective' => 'x', 'cycle' => 1, 'tester_model' => 'google/gemini-2.5-flash'])
        ->assertOk();
});
