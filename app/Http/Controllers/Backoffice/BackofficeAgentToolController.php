<?php

namespace App\Http\Controllers\Backoffice;

use App\Enums\AgentToolCapability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\UpdateAgentToolsRequest;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\ToolDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tools tab of the agent cockpit: the native capabilities
 * (App\Enums\AgentToolCapability, stored on AgentConfig) and the webhook tools
 * (ToolDefinition rows, toggled through `is_active`) of a single agent.
 *
 * Cross-tenant isolation comes from route-model binding: while a company is
 * active the tenant global scope makes another company's agent 404. Everything
 * below therefore keys off the agent's own tenant_id.
 */
class BackofficeAgentToolController extends Controller
{
    public function edit(Agent $agent): Response
    {
        return Inertia::render('backoffice/agents/Tools', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
            ],
            'capabilities' => AgentToolCapability::options(),
            'enabled' => $this->enabledCapabilities($agent),
            /** False while the agent still runs on the unrestricted default. */
            'restricted' => is_array($this->configRowFor($agent)?->tool_capabilities),
            'webhooks' => $this->webhooksFor($agent)
                ->map(fn (ToolDefinition $tool): array => [
                    'id' => $tool->id,
                    'name' => (string) $tool->name,
                    'slug' => (string) $tool->slug,
                    'description' => $tool->description,
                    'is_active' => (bool) $tool->is_active,
                    /** Company-wide definitions (agent_id null) are shared by every agent. */
                    'is_shared' => $tool->agent_id === null,
                ])
                ->values(),
        ]);
    }

    public function update(UpdateAgentToolsRequest $request, Agent $agent): RedirectResponse
    {
        $validated = $request->validated();

        $this->saveCapabilities($agent, $validated['capabilities']);
        $this->saveWebhooks($agent, $validated['webhooks'] ?? []);

        return back()->with('success', 'Ferramentas do agente atualizadas.');
    }

    /**
     * Saving freezes the selection: from here on the agent only gets the listed
     * native tools. The 300s `agent_config_id_{id}` cache is busted by
     * AgentConfig's `saved` hook, so the next turn already runs on the new set.
     *
     * @param  list<string>  $capabilities
     */
    private function saveCapabilities(Agent $agent, array $capabilities): void
    {
        $config = AgentConfig::query()
            ->withoutGlobalScope('tenant')
            ->firstOrNew(['agent_id' => $agent->id]);

        if (! $config->exists) {
            /** Same seed BackofficeAgentModelController uses when materialising a missing row. */
            $config->fill([
                'tenant_id' => $agent->tenant_id,
                'agent_name' => $agent->name,
                'agent_niche' => 'generic',
            ]);
        }

        $config->tool_capabilities = array_values(array_unique($capabilities));
        $config->save();
    }

    /**
     * Toggles are applied row by row (never a mass builder update) so the
     * ToolDefinition `saved` hook bumps the tenant's prompt-layer cache version.
     *
     * @param  list<array{id: int, is_active: bool}>  $webhooks
     */
    private function saveWebhooks(Agent $agent, array $webhooks): void
    {
        if ($webhooks === []) {
            return;
        }

        $definitions = $this->webhooksFor($agent)->keyBy('id');

        foreach ($webhooks as $webhook) {
            $definition = $definitions->get($webhook['id']);

            if ($definition === null) {
                continue;
            }

            $definition->is_active = $webhook['is_active'];
            $definition->save();
        }
    }

    /**
     * Webhook tools this agent can call: its own plus the company-wide ones
     * (`agent_id` null), mirroring ToolDefinition::scopeForAgent at runtime.
     * Scoped to the agent's tenant, so an id from another company is dropped.
     *
     * @return Collection<int, ToolDefinition>
     */
    private function webhooksFor(Agent $agent): Collection
    {
        return ToolDefinition::query()
            ->forTenant((string) $agent->tenant_id)
            ->forAgent($agent->id)
            ->where('type', 'webhook')
            ->orderBy('name')
            ->get();
    }

    /**
     * The capabilities in force. A config that never stored a selection means
     * "no restriction", which the runtime treats as every native tool enabled —
     * so the screen shows them all checked.
     *
     * @return list<string>
     */
    private function enabledCapabilities(Agent $agent): array
    {
        $stored = $this->configRowFor($agent)?->tool_capabilities;

        if (! is_array($stored)) {
            return AgentToolCapability::values();
        }

        return array_values(array_map(strval(...), $stored));
    }

    /**
     * The agent is already tenant-checked by route-model binding, so the config
     * row is fetched by agent_id alone — legacy rows predating `tenant_id`
     * would be invisible to the scoped query and get duplicated on save.
     */
    private function configRowFor(Agent $agent): ?AgentConfig
    {
        return AgentConfig::query()
            ->withoutGlobalScope('tenant')
            ->where('agent_id', $agent->id)
            ->first();
    }
}
