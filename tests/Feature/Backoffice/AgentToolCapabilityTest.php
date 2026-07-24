<?php

use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\GenericAgent;
use App\Ai\Tools\GenericWebhookTool;
use App\Enums\AgentToolCapability;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\ToolDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function toolsSuperAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    return $admin;
}

function toolsAgentIn(Tenant $tenant): Agent
{
    return Agent::factory()->create(['tenant_id' => $tenant->id]);
}

/**
 * @param  list<string>|null  $capabilities
 */
function toolsLeadFor(Agent $agent, ?array $capabilities): Lead
{
    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $agent->tenant_id,
        'tool_capabilities' => $capabilities,
    ]);

    return Lead::factory()->forAgent($agent)->create();
}

/**
 * The capability names of a resolved toolset, ignoring tools that no capability
 * governs (webhooks).
 *
 * @return list<string>
 */
function toolCapabilityNames(iterable $tools): array
{
    $names = [];

    foreach ($tools as $tool) {
        $capability = AgentToolCapability::fromToolClass($tool::class);

        if ($capability !== null) {
            $names[] = $capability->value;
        }
    }

    return $names;
}

function toolsWebhook(Tenant $tenant, ?Agent $agent = null, bool $isActive = true): ToolDefinition
{
    return ToolDefinition::create([
        'tenant_id' => (string) $tenant->id,
        'agent_id' => $agent?->id,
        'slug' => 'consultar_estoque',
        'name' => 'Consultar estoque',
        'description' => 'Consulta o estoque no ERP da empresa.',
        'type' => 'webhook',
        'config' => ['url' => 'https://example.test/estoque', 'method' => 'POST'],
        'is_active' => $isActive,
    ]);
}

// ── Runtime ───────────────────────────────────────────────────────────────────

it('keeps the whole toolset when no selection was ever saved', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $lead = toolsLeadFor(toolsAgentIn($tenant), null);

    expect(toolCapabilityNames((new CredFlowAgent($lead))->tools()))
        ->toContain('consultar_credito_inss')
        ->toContain('escalar_para_humano')
        ->toContain('atualizar_status_lead');
});

it('drops a native tool the operator disabled', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $lead = toolsLeadFor(toolsAgentIn($tenant), [
        'registrar_informacao_contato',
        'atualizar_status_lead',
    ]);

    $names = toolCapabilityNames((new CredFlowAgent($lead))->tools());

    expect($names)->toContain('atualizar_status_lead')
        ->and($names)->not->toContain('consultar_credito_inss')
        ->and($names)->not->toContain('escalar_para_humano');
});

it('disables every native tool on an empty selection', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $lead = toolsLeadFor(toolsAgentIn($tenant), []);

    expect(toolCapabilityNames((new CredFlowAgent($lead))->tools()))->toBe([]);
});

it('never filters out webhook tools, which the ToolDefinition toggle owns', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = toolsAgentIn($tenant);
    $lead = toolsLeadFor($agent, []);
    toolsWebhook($tenant, $agent);

    $tools = iterator_to_array((new GenericAgent($lead))->tools());

    expect(toolCapabilityNames($tools))->toBe([])
        ->and($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(GenericWebhookTool::class);
});

// ── Reading the screen ────────────────────────────────────────────────────────

it('shows every capability enabled for an agent without a saved selection', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = toolsAgentIn($tenant);
    toolsWebhook($tenant, $agent);

    $this->actingAs(toolsSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->get(route('backoffice.agents.tools.edit', $agent))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/agents/Tools')
            ->where('restricted', false)
            ->where('enabled', AgentToolCapability::values())
            ->has('capabilities', count(AgentToolCapability::cases()))
            ->has('webhooks', 1)
            ->where('webhooks.0.is_shared', false)
        );
});

it('blocks a non-super-admin from the tools screen', function () {
    /** The agent belongs to the user's own tenant, so the 403 comes from the gate and not from route-model binding. */
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['tenant_id' => $user->tenantId]);

    $this->actingAs($user)
        ->get(route('backoffice.agents.tools.edit', $agent))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('backoffice.agents.tools.update', $agent), ['capabilities' => []])
        ->assertForbidden();
});

// ── Writing ───────────────────────────────────────────────────────────────────

it('persists the capability selection and the next turn honours it', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = toolsAgentIn($tenant);
    $lead = toolsLeadFor($agent, null);

    /** Warms the 300s agent_config_id_{id} cache, which the save must bust. */
    expect(toolCapabilityNames((new CredFlowAgent($lead))->tools()))
        ->toContain('escalar_para_humano');

    $this->actingAs(toolsSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->put(route('backoffice.agents.tools.update', $agent), [
            'capabilities' => ['consultar_credito_inss', 'atualizar_status_lead'],
        ])
        ->assertRedirect();

    $config = AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->firstOrFail();

    expect($config->tool_capabilities)->toBe(['consultar_credito_inss', 'atualizar_status_lead'])
        ->and(toolCapabilityNames((new CredFlowAgent($lead->fresh()))->tools()))
        ->not->toContain('escalar_para_humano');
});

it('creates the config row when the agent has none', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = toolsAgentIn($tenant);

    $this->actingAs(toolsSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->put(route('backoffice.agents.tools.update', $agent), [
            'capabilities' => ['escalar_para_humano'],
        ])
        ->assertRedirect();

    $config = AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->firstOrFail();

    expect($config->tool_capabilities)->toBe(['escalar_para_humano'])
        ->and((string) $config->tenant_id)->toBe((string) $tenant->id);
});

it('turns a webhook off and the next turn stops loading it', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = toolsAgentIn($tenant);
    $lead = toolsLeadFor($agent, null);
    $webhook = toolsWebhook($tenant, $agent);

    /** Warms the tenant-versioned webhook cache, which the toggle must bump. */
    expect(iterator_to_array((new GenericAgent($lead))->tools()))->toHaveCount(4);

    $this->actingAs(toolsSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->put(route('backoffice.agents.tools.update', $agent), [
            'capabilities' => AgentToolCapability::values(),
            'webhooks' => [['id' => $webhook->id, 'is_active' => false]],
        ])
        ->assertRedirect();

    $tools = iterator_to_array((new GenericAgent($lead->fresh()))->tools());

    expect($webhook->fresh()->is_active)->toBeFalse()
        ->and(array_filter($tools, fn ($tool) => $tool instanceof GenericWebhookTool))->toBe([]);
});

// ── Cross-tenant isolation ────────────────────────────────────────────────────

it('cannot open the tools of an agent of another company', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);
    $agentB = toolsAgentIn($tenantB);

    $this->actingAs(toolsSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenantA->id])
        ->get(route('backoffice.agents.tools.edit', $agentB))
        ->assertNotFound();
});

it('cannot write the tools of an agent of another company', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);
    $agentB = toolsAgentIn($tenantB);
    toolsLeadFor($agentB, null);

    $this->actingAs(toolsSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenantA->id])
        ->put(route('backoffice.agents.tools.update', $agentB), ['capabilities' => []])
        ->assertNotFound();

    $config = AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agentB->id)->firstOrFail();

    expect($config->tool_capabilities)->toBeNull();
});

it('ignores a webhook id belonging to another company', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);
    $agentA = toolsAgentIn($tenantA);
    $webhookB = toolsWebhook($tenantB, toolsAgentIn($tenantB));

    $this->actingAs(toolsSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenantA->id])
        ->put(route('backoffice.agents.tools.update', $agentA), [
            'capabilities' => [],
            'webhooks' => [['id' => $webhookB->id, 'is_active' => false]],
        ])
        ->assertRedirect();

    expect($webhookB->fresh()->is_active)->toBeTrue();
});

// ── Validation ────────────────────────────────────────────────────────────────

it('rejects an unknown capability', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = toolsAgentIn($tenant);

    $this->actingAs(toolsSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->put(route('backoffice.agents.tools.update', $agent), [
            'capabilities' => ['apagar_banco_de_dados'],
        ])
        ->assertSessionHasErrors('capabilities.0');

    expect(AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->exists())->toBeFalse();
});

it('rejects a missing capability list so a bad payload never wipes the toolset', function () {
    $tenant = Tenant::create(['name' => 'Org A']);
    $agent = toolsAgentIn($tenant);

    $this->actingAs(toolsSuperAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->put(route('backoffice.agents.tools.update', $agent), [])
        ->assertSessionHasErrors('capabilities');
});
