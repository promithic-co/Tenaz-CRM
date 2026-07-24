<?php

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgentConfigResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function backofficeSuperAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    return $admin;
}

function agentIn(Tenant $tenant): Agent
{
    return Agent::factory()->create(['tenant_id' => $tenant->id]);
}

// ── Access ────────────────────────────────────────────────────────────────────

it('lists the agents of the active company', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);

    $agentA = agentIn($tenantA);
    $agentB = agentIn($tenantB);

    $this->actingAs(backofficeSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenantA->id])
        ->get(route('backoffice.agents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/agents/Index')
            ->has('agents', 1)
            ->where('agents.0.id', $agentA->id)
        );

    expect($agentB->tenant_id)->not->toBe($agentA->tenant_id);
});

it('lists no agents when no company is selected', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    agentIn($tenant);

    $this->actingAs(backofficeSuperAdmin())
        ->get(route('backoffice.agents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('agents', 0));
});

it('blocks a non-super-admin from the agent cockpit', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('backoffice.agents.index'))
        ->assertForbidden();
});

it('shows the effective model of an agent without its own config row', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = agentIn($tenant);

    $this->actingAs(backofficeSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->get(route('backoffice.agents.show', $agent))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/agents/Show')
            ->where('model.has_config_row', false)
            ->where('effective.agent_model', fn ($model) => filled($model))
        );
});

// ── Writing ───────────────────────────────────────────────────────────────────

it('updates the LLM model of an agent of the active company', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = agentIn($tenant);
    AgentConfig::create([
        'agent_id' => $agent->id,
        'tenant_id' => $tenant->id,
        'agent_provider' => 'openai',
        'agent_model' => 'gpt-4o-mini',
        'temperature' => 0.4,
    ]);

    $this->actingAs(backofficeSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.model.update', $agent), [
            'agent_provider' => 'openrouter',
            'agent_model' => 'anthropic/claude-haiku-4-5',
            'temperature' => 0.15,
        ])
        ->assertRedirect();

    $config = AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->firstOrFail();

    expect($config->agent_provider)->toBe('openrouter')
        ->and($config->agent_model)->toBe('anthropic/claude-haiku-4-5')
        ->and($config->temperature)->toBe(0.15);
});

it('creates the config row when the agent has none, without touching other fields', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = agentIn($tenant);

    $this->actingAs(backofficeSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.model.update', $agent), [
            'agent_provider' => 'groq',
            'agent_model' => 'llama-3.3-70b',
            'temperature' => 1,
        ])
        ->assertRedirect();

    $config = AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->firstOrFail();

    expect($config->agent_model)->toBe('llama-3.3-70b')
        ->and((string) $config->tenant_id)->toBe((string) $tenant->id)
        ->and($config->agent_name)->toBe($agent->name);
});

it('busts the resolver cache so the next turn uses the new model', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = agentIn($tenant);
    AgentConfig::create([
        'agent_id' => $agent->id,
        'tenant_id' => $tenant->id,
        'agent_provider' => 'openai',
        'agent_model' => 'gpt-4o-mini',
    ]);

    $resolver = app(AgentConfigResolver::class);

    expect($resolver->forAgentId($agent->id)['agent_model'])->toBe('gpt-4o-mini')
        ->and(Cache::has("agent_config_id_{$agent->id}"))->toBeTrue();

    $this->actingAs(backofficeSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.model.update', $agent), [
            'agent_provider' => 'openrouter',
            'agent_model' => 'anthropic/claude-haiku-4-5',
            'temperature' => 0.3,
        ]);

    expect($resolver->forAgentId($agent->id)['agent_model'])->toBe('anthropic/claude-haiku-4-5');
});

// ── Cross-tenant isolation ────────────────────────────────────────────────────

it('cannot open an agent of another company while acting as one', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);
    $agentB = agentIn($tenantB);

    $this->actingAs(backofficeSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenantA->id])
        ->get(route('backoffice.agents.show', $agentB))
        ->assertNotFound();
});

it('cannot write the model of an agent of another company', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);
    $agentB = agentIn($tenantB);
    AgentConfig::create([
        'agent_id' => $agentB->id,
        'tenant_id' => $tenantB->id,
        'agent_provider' => 'openai',
        'agent_model' => 'gpt-4o-mini',
    ]);

    $this->actingAs(backofficeSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenantA->id])
        ->patch(route('backoffice.agents.model.update', $agentB), [
            'agent_provider' => 'groq',
            'agent_model' => 'llama-3.3-70b',
            'temperature' => 0.5,
        ])
        ->assertNotFound();

    $config = AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agentB->id)->firstOrFail();

    expect($config->agent_model)->toBe('gpt-4o-mini');
});

// ── Validation ────────────────────────────────────────────────────────────────

it('rejects a provider outside the whitelist', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = agentIn($tenant);

    $this->actingAs(backofficeSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.model.update', $agent), [
            'agent_provider' => 'evil-provider',
            'agent_model' => 'gpt-4o-mini',
            'temperature' => 0.4,
        ])
        ->assertSessionHasErrors('agent_provider');

    expect(AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->exists())->toBeFalse();
});

it('rejects a blank model and an out-of-range temperature', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = agentIn($tenant);

    $this->actingAs(backofficeSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.model.update', $agent), [
            'agent_provider' => 'openai',
            'agent_model' => '   ',
            'temperature' => 5,
        ])
        ->assertSessionHasErrors(['agent_model', 'temperature']);
});
