<?php

use App\Ai\Agents\BlindspotScannerAgent;
use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\ScenarioGeneratorAgent;
use App\Models\Lead;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('BlindspotScannerAgent resolves provider, model and the configured instructions', function () {
    $agent = new BlindspotScannerAgent('openrouter', 'anthropic/claude-3.5');

    expect($agent->provider())->toBe('openrouter')
        ->and($agent->model())->toBe('anthropic/claude-3.5')
        ->and($agent->instructions())->toBe('Você é um Red Team AI expert.')
        ->and($agent->instructions())->toBe(config('playground_prompts.blindspot_scanner_instructions'));
});

test('ScenarioGeneratorAgent resolves provider, model and the configured instructions', function () {
    $agent = new ScenarioGeneratorAgent('openai', 'gpt-4o');

    expect($agent->provider())->toBe('openai')
        ->and($agent->model())->toBe('gpt-4o')
        ->and($agent->instructions())->toBe('Exija desafios em nivel operacional.')
        ->and($agent->instructions())->toBe(config('playground_prompts.scenario_generator_instructions'));
});

test('CredFlowAgent with a prompt override returns the override as instructions', function () {
    $lead = Lead::factory()->sandbox()->create();

    $agent = new CredFlowAgent($lead, 'Instrução totalmente customizada do sandbox.');

    expect((string) $agent->instructions())->toBe('Instrução totalmente customizada do sandbox.');
});

test('CredFlowAgent without an override falls back to the normal prompt', function () {
    $lead = Lead::factory()->sandbox()->create();

    $agent = new CredFlowAgent($lead);
    $normal = (string) $agent->instructions();

    expect($normal)->not->toBe('Instrução totalmente customizada do sandbox.')
        ->and($normal)->not->toBe('');
});

test('CredFlowAgent with a null override behaves like the bare constructor', function () {
    $lead = Lead::factory()->sandbox()->create();

    $withNull = (string) (new CredFlowAgent($lead, null))->instructions();
    $bare = (string) (new CredFlowAgent($lead))->instructions();

    expect($withNull)->toBe($bare);
});
