<?php

use App\Ai\Agents\BlindspotScannerAgent;
use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\ScenarioGeneratorAgent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;

uses(RefreshDatabase::class);

/** The playground is gated by `super_admin`; these tests exercise the endpoints past that gate. */
function allowlistOperator(): User
{
    $user = userWithTenant();
    $user->forceFill(['is_super_admin' => true])->save();

    return $user->fresh();
}

/** F8: the LLM-invoking playground endpoints must reject provider/model injection. */
function allowlistSandboxLead(User $user): Lead
{
    return Lead::factory()->sandbox()->create(['tenant_id' => (string) $user->tenantId]);
}

test('chat rejects a model_override outside the playground allow-list', function () {
    $user = allowlistOperator();
    $lead = allowlistSandboxLead($user);

    $this->actingAs($user)
        ->postJson(route('backoffice.playground.chat', $lead), ['message' => 'oi', 'model_override' => 'evil/gpt-9000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('model_override');
});

test('chat accepts an allow-listed model_override', function () {
    $user = allowlistOperator();
    $lead = allowlistSandboxLead($user);
    Ai::fakeAgent(CredFlowAgent::class, ['ok']);

    $this->actingAs($user)
        ->postJson(route('backoffice.playground.chat', $lead), ['message' => 'oi', 'model_override' => 'anthropic/claude-haiku-4-5'])
        ->assertOk();
});

test('scanBlindspots rejects a tester_model outside the allow-list', function () {
    $user = allowlistOperator();
    allowlistSandboxLead($user);

    $this->actingAs($user)
        ->postJson(route('backoffice.playground.scanBlindspots'), ['tester_model' => 'evil/model'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tester_model');
});

test('scanBlindspots accepts an allow-listed tester_model', function () {
    $user = allowlistOperator();
    allowlistSandboxLead($user);
    Ai::fakeAgent(BlindspotScannerAgent::class, [json_encode([['category' => 'c', 'scenario' => 's', 'severity' => 'low', 'target' => 't']])]);

    $this->actingAs($user)
        ->postJson(route('backoffice.playground.scanBlindspots'), ['tester_model' => 'gpt-4o'])
        ->assertOk();
});

test('generateScenario rejects a tester_model outside the allow-list', function () {
    $user = allowlistOperator();

    $this->actingAs($user)
        ->postJson(route('backoffice.playground.generateScenario'), ['objective' => 'x', 'cycle' => 1, 'tester_model' => 'evil/model'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tester_model');
});

test('generateScenario accepts an allow-listed tester_model', function () {
    $user = allowlistOperator();
    Ai::fakeAgent(ScenarioGeneratorAgent::class, ['cenário']);

    $this->actingAs($user)
        ->postJson(route('backoffice.playground.generateScenario'), ['objective' => 'x', 'cycle' => 1, 'tester_model' => 'google/gemini-2.5-flash'])
        ->assertOk();
});
