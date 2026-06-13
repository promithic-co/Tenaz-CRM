<?php

use App\Models\AgentTemplateConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function superAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    return $admin;
}

function validTemplatePayload(array $overrides = []): array
{
    return array_merge([
        'template_slug' => 'inss',
        'agent_provider' => 'openrouter',
        'agent_model' => 'openai/gpt-4o',
        'transcription_provider' => 'openai',
        'transcription_model' => 'whisper-1',
        'vision_provider' => 'openai',
        'vision_model' => 'gpt-4o',
        'temperature' => 0.4,
        'max_tokens' => 1024,
        'max_conversation_messages' => 24,
    ], $overrides);
}

// ── SC1: index returns templates list ─────────────────────────────────────────

it('super-admin GET templates.index returns 200 with template rows', function () {
    AgentTemplateConfig::factory()->create(['template_slug' => 'inss']);

    $this->actingAs(superAdmin())
        ->get(route('backoffice.templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/templates/Index')
            ->has('templates', 1)
            ->where('templates.0.template_slug', 'inss')
        );
});

it('template row in index exposes current agent_model', function () {
    AgentTemplateConfig::factory()->create([
        'template_slug' => 'inss',
        'agent_model' => 'anthropic/claude-haiku-4-5',
    ]);

    $this->actingAs(superAdmin())
        ->get(route('backoffice.templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('templates.0.agent_model', 'anthropic/claude-haiku-4-5')
        );
});

// ── SC2: update persists new values ───────────────────────────────────────────

it('super-admin PUT templates.update persists new agent_model', function () {
    AgentTemplateConfig::factory()->create(['template_slug' => 'inss']);

    $this->actingAs(superAdmin())
        ->put(route('backoffice.templates.update', ['template_slug' => 'inss']), validTemplatePayload(['agent_model' => 'openai/gpt-4o']))
        ->assertRedirect();

    expect(AgentTemplateConfig::where('template_slug', 'inss')->first()->agent_model)
        ->toBe('openai/gpt-4o');
});

// ── SC3a: invalid provider → redirect with errors, DB untouched ───────────────

it('PUT with invalid agent_provider redirects with validation error and leaves DB row untouched', function () {
    AgentTemplateConfig::factory()->create([
        'template_slug' => 'inss',
        'agent_model' => 'anthropic/claude-haiku-4-5',
    ]);

    $original = AgentTemplateConfig::where('template_slug', 'inss')->first()->agent_model;

    $this->actingAs(superAdmin())
        ->put(
            route('backoffice.templates.update', ['template_slug' => 'inss']),
            validTemplatePayload(['agent_provider' => 'invalid-provider'])
        )
        ->assertRedirect()
        ->assertSessionHasErrors(['agent_provider']);

    expect(AgentTemplateConfig::where('template_slug', 'inss')->first()->agent_model)
        ->toBe($original);
});

// ── SC3b: whitespace-only model → redirect with errors, DB untouched ─────────

it('PUT with whitespace-only agent_model redirects with validation error and leaves DB row untouched', function () {
    AgentTemplateConfig::factory()->create([
        'template_slug' => 'inss',
        'agent_model' => 'anthropic/claude-haiku-4-5',
    ]);

    $original = AgentTemplateConfig::where('template_slug', 'inss')->first()->agent_model;

    $this->actingAs(superAdmin())
        ->put(
            route('backoffice.templates.update', ['template_slug' => 'inss']),
            validTemplatePayload(['agent_model' => '   '])
        )
        ->assertRedirect()
        ->assertSessionHasErrors(['agent_model']);

    expect(AgentTemplateConfig::where('template_slug', 'inss')->first()->agent_model)
        ->toBe($original);
});

// ── Privilege-escalation guard ────────────────────────────────────────────────

it('non-super-admin GET templates.index receives 403', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('backoffice.templates.index'))
        ->assertForbidden();
});

it('non-super-admin PUT templates.update receives 403', function () {
    AgentTemplateConfig::factory()->create(['template_slug' => 'inss']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('backoffice.templates.update', ['template_slug' => 'inss']), validTemplatePayload())
        ->assertForbidden();
});
