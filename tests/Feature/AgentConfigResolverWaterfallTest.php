<?php

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\AgentTemplateConfig;
use App\Models\AppSetting;
use App\Services\AgentConfigResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

// ─── Test A: template fills empty-string client fields (LLM-02 layer 2) ──────

test('template fills empty-string client LLM fields', function () {
    $agent = Agent::factory()->create();

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'template_slug' => 'alicia-receptivo',
        'agent_provider' => '',
        'agent_model' => '',
    ]);

    AgentTemplateConfig::factory()->create([
        'template_slug' => 'alicia-receptivo',
        'agent_provider' => 'openrouter',
        'agent_model' => 'anthropic/claude-haiku-4-5',
    ]);

    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);

    expect($resolved['agent_provider'])->toBe('openrouter');
    expect($resolved['agent_model'])->toBe('anthropic/claude-haiku-4-5');
});

// ─── Test B: client field wins over template (LLM-03 backward compat) ────────

test('client LLM field wins over template value when populated (backward compat)', function () {
    $agent = Agent::factory()->create();

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'template_slug' => 'alicia-receptivo',
        'agent_model' => 'openai/gpt-5-custom',
    ]);

    AgentTemplateConfig::factory()->create([
        'template_slug' => 'alicia-receptivo',
        'agent_model' => 'anthropic/claude-haiku-4-5',
    ]);

    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);

    expect($resolved['agent_model'])->toBe('openai/gpt-5-custom');
});

// ─── Test C: last-resort when template missing or slug null ──────────────────

test('last-resort fallback when template_slug is null and agent LLM fields are empty', function () {
    $agent = Agent::factory()->create();

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'template_slug' => null,
        'agent_model' => '',
        'agent_provider' => '',
    ]);

    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);

    expect($resolved['agent_model'])->toBe(config('credflow.agent.fallback_model'));
    expect($resolved['agent_provider'])->toBe(config('credflow.agent.fallback_provider'));
    expect($resolved['agent_model'])->not->toBeNull()->not->toBe('');
});

test('last-resort fallback when slug is nonexistent and agent_model is empty', function () {
    $agent = Agent::factory()->create();

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'template_slug' => 'nonexistent-slug',
        'agent_model' => '',
        'agent_provider' => '',
    ]);

    // No AgentTemplateConfig row for 'nonexistent-slug'

    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);

    expect($resolved['agent_model'])->toBe(config('credflow.agent.fallback_model'));
    expect($resolved['agent_model'])->not->toBeNull()->not->toBe('');
});

// ─── Test D: observer busts cache; template change propagates (LLM-05) ───────

test('template config update propagates to next resolve after observer busts cache', function () {
    $agent = Agent::factory()->create();

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'template_slug' => 'aria-bulk',
        'agent_model' => '',
    ]);

    $templateConfig = AgentTemplateConfig::factory()->create([
        'template_slug' => 'aria-bulk',
        'agent_model' => 'model-old',
    ]);

    // First resolve — should get 'model-old'
    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);
    expect($resolved['agent_model'])->toBe('model-old');

    // Update template (fires saved observer → busts cache key)
    $templateConfig->update(['agent_model' => 'model-new']);

    // Bust per-agent cache to force fresh resolve (simulates TTL expiry or new request)
    Cache::forget("agent_config_id_{$agent->id}");
    $resolvedAfter = app(AgentConfigResolver::class)->forAgentId($agent->id);
    expect($resolvedAfter['agent_model'])->toBe('model-new');
});

// ─── Test E: null-safety invariant (LLM-02 core goal) ────────────────────────

test('agent_provider and agent_model are non-empty after resolve when client has empty LLM fields', function () {
    $agent = Agent::factory()->create();

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'template_slug' => null,
        'agent_provider' => '',
        'agent_model' => '',
    ]);

    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);

    expect($resolved['agent_provider'])->not->toBeNull()->not->toBe('');
    expect($resolved['agent_model'])->not->toBeNull()->not->toBe('');
});

// ─── Test F: NUMERIC-ZERO preservation ───────────────────────────────────────

test('temperature 0.0 client value is preserved and NOT overwritten by template 0.7 (numeric-zero guard)', function () {
    $agent = Agent::factory()->create();

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'template_slug' => 'alicia-receptivo',
        'temperature' => 0.0,
        'agent_model' => '',
    ]);

    AgentTemplateConfig::factory()->create([
        'template_slug' => 'alicia-receptivo',
        'temperature' => 0.7,
        'agent_model' => 'anthropic/claude-haiku-4-5',
    ]);

    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);

    // Client temperature=0.0 must be preserved (NOT overwritten by template 0.7)
    expect($resolved['temperature'])->toBe(0.0);
    // The genuinely-empty field should still be filled from template
    expect($resolved['agent_model'])->toBe('anthropic/claude-haiku-4-5');
});

// ─── Test F2: NUMERIC-ZERO cast contract for integer fields (WR-02) ──────────

test('max_tokens 0 client value is preserved and NOT overwritten by template (cast contract)', function () {
    $agent = Agent::factory()->create();

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'template_slug' => 'alicia-receptivo',
        'max_tokens' => 0,
        'agent_model' => '',
    ]);

    AgentTemplateConfig::factory()->create([
        'template_slug' => 'alicia-receptivo',
        'max_tokens' => 4096,
        'agent_model' => 'anthropic/claude-haiku-4-5',
    ]);

    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);

    // AgentConfig casts max_tokens to int, so DB 0 arrives as int 0 (not "" / "0")
    // and the `=== '' ` guard keeps it instead of filling from the template's 4096.
    expect($resolved['max_tokens'])->toBe(0);
    // The genuinely-empty string field is still filled from the template.
    expect($resolved['agent_model'])->toBe('anthropic/claude-haiku-4-5');
});

// ─── Test G: empty config fallback falls through to literal constant (WR-01 / T-56-05) ───

test('empty CREDFLOW_AGENT_FALLBACK_MODEL env still resolves a non-empty model via literal constant', function () {
    // Simulate an operator setting the env var empty — the config-level fallback is now ''
    config(['credflow.agent.fallback_model' => '']);
    config(['credflow.agent.fallback_provider' => '']);

    $agent = Agent::factory()->create();

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'template_slug' => null,
        'agent_provider' => '',
        'agent_model' => '',
    ]);

    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);

    // Must coalesce past the empty config to the hard literal — never empty
    expect($resolved['agent_model'])->toBe('anthropic/claude-haiku-4-5');
    expect($resolved['agent_provider'])->toBe('openrouter');
    expect($resolved['agent_model'])->not->toBeNull()->not->toBe('');
});

// ─── Test H: legacy fallback path is null-safe (WR-03 / T-56-05) ──────────────

test('legacy fallback path (null agentId) never returns an empty agent_model', function () {
    // No AgentConfig row → forAgentId falls through to legacyFallback(), which bypasses
    // mergeTemplateLlmDefaults. The model must still be non-empty.
    config(['credflow.agent.fallback_model' => '']);
    config(['credflow.agent.fallback_provider' => '']);

    $resolved = app(AgentConfigResolver::class)->forAgentId(null);

    expect($resolved['agent_model'])->not->toBeNull()->not->toBe('');
    expect($resolved['agent_provider'])->not->toBeNull()->not->toBe('');
});

test('legacy fallback path casts extra integer settings while preserving string settings', function () {
    AppSetting::set('followup_first_delay_minutes', '17');
    AppSetting::set('followup_max_count', '6');
    AppSetting::set('followup_interval_days', '3');
    AppSetting::set('followup_persuasion_intensity', '5');
    AppSetting::set('transcription_model', 'custom-transcription-model');

    $resolved = app(AgentConfigResolver::class)->forAgentId(null);

    expect($resolved['followup_first_delay_minutes'])->toBe(17);
    expect($resolved['followup_max_count'])->toBe(6);
    expect($resolved['followup_interval_days'])->toBe(3);
    expect($resolved['followup_persuasion_intensity'])->toBe(5);
    expect($resolved['transcription_model'])->toBe('custom-transcription-model');
});

// ─── Slice 5: AgentConfig writes bust the per-agent cache automatically ──────

test('direct AgentConfig save busts the agent_config_id cache without manual forget', function () {
    $agent = Agent::factory()->create();
    $config = AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'agent_name' => 'Antes',
    ]);

    $resolved = app(AgentConfigResolver::class)->forAgentId($agent->id);
    expect($resolved['agent_name'])->toBe('Antes');

    $config->update(['agent_name' => 'Depois']);

    $resolvedAfter = app(AgentConfigResolver::class)->forAgentId($agent->id);
    expect($resolvedAfter['agent_name'])->toBe('Depois');
});

test('AgentConfig delete busts the agent_config_id cache', function () {
    $agent = Agent::factory()->create();
    $config = AgentConfig::factory()->create(['agent_id' => $agent->id]);

    app(AgentConfigResolver::class)->forAgentId($agent->id);
    expect(Cache::has("agent_config_id_{$agent->id}"))->toBeTrue();

    $config->delete();

    expect(Cache::has("agent_config_id_{$agent->id}"))->toBeFalse();
});
