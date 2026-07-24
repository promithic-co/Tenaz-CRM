<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Services\ActiveTenant;
use App\Services\AgentConfigResolver;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Inertia\Inertia;
use Inertia\Response;

class BackofficeAgentController extends Controller
{
    public function __construct(
        private readonly ActiveTenant $activeTenant,
        private readonly AgentConfigResolver $configResolver,
    ) {}

    /**
     * Lists the agents of the active company. The tenant global scope already
     * restricts the query, so the empty state below is about guidance, not
     * isolation: with no company selected the list would span every tenant,
     * which is meaningless for a per-agent cockpit.
     */
    public function index(): Response
    {
        $tenant = $this->activeTenant->tenant();

        $agents = $tenant === null
            ? []
            : Agent::query()
                ->with(['config' => fn (HasOne $query) => $query
                    ->withoutGlobalScope('tenant')
                    ->select(['id', 'agent_id', 'template_slug'])])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'is_active', 'is_default'])
                ->map(fn (Agent $agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'slug' => $agent->slug,
                    'is_active' => $agent->is_active,
                    'is_default' => $agent->is_default,
                    'template_slug' => $agent->config?->template_slug,
                    /** What the runtime actually uses, after the config → template → hard-default waterfall. */
                    'effective' => $this->effectiveLlmConfig($agent),
                ])
                ->all();

        return Inertia::render('backoffice/agents/Index', [
            'agents' => $agents,
        ]);
    }

    public function show(Agent $agent): Response
    {
        $config = $this->configRowFor($agent);

        return Inertia::render('backoffice/agents/Show', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'is_active' => $agent->is_active,
                'template_slug' => $config?->template_slug,
            ],
            'model' => [
                'agent_provider' => $config?->agent_provider,
                'agent_model' => $config?->agent_model,
                'temperature' => $config?->temperature,
                'has_config_row' => $config !== null,
            ],
            'effective' => $this->effectiveLlmConfig($agent),
            'providerWhitelist' => config('credflow.agent.provider_whitelist'),
            'modelSuggestions' => config('credflow.agent.playground_models'),
        ]);
    }

    /**
     * @return array{agent_provider: string|null, agent_model: string|null, temperature: float|null}
     */
    private function effectiveLlmConfig(Agent $agent): array
    {
        $resolved = $this->configResolver->forAgentId($agent->id, $this->tenantIdAsUserId($agent));

        return [
            'agent_provider' => $resolved['agent_provider'] ?? null,
            'agent_model' => $resolved['agent_model'] ?? null,
            'temperature' => isset($resolved['temperature']) ? (float) $resolved['temperature'] : null,
        ];
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

    /** Mirrors AgentConfigResolver's own lead → user-id fallback for the legacy AppSetting layer. */
    private function tenantIdAsUserId(Agent $agent): ?int
    {
        return is_numeric($agent->tenant_id) ? (int) $agent->tenant_id : null;
    }
}
