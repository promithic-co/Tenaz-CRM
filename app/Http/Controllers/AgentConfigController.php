<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAgentScopedConfigRequest;
use App\Models\Agent;
use App\Models\AgentConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AgentConfigController extends Controller
{
    public function show(Agent $agent): Response
    {
        $this->authorize('manage', $agent);

        $config = AgentConfig::firstOrCreate(
            ['agent_id' => $agent->id],
            ['tenant_id' => $agent->tenant_id, 'agent_name' => $agent->name, 'agent_niche' => 'inss']
        );

        return Inertia::render('agente-config/Index', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
            ],
            'settings' => [
                'agent_niche' => $config->agent_niche ?? 'inss',
                'agent_name' => $config->agent_name,
                'company_name' => $config->company_name,
                'agent_personality' => $config->agent_personality,
                'agent_greeting' => $config->agent_greeting,
            ],
            'specializations' => $this->specializations(),
            'flash' => session('success'),
        ]);
    }

    public function update(Agent $agent, UpdateAgentScopedConfigRequest $request): RedirectResponse
    {
        $this->authorize('manage', $agent);

        $payload = $request->validated();

        AgentConfig::updateOrCreate(
            ['agent_id' => $agent->id],
            array_merge($payload, ['tenant_id' => $agent->tenant_id])
        );

        Cache::forget("agent_config_id_{$agent->id}");

        return back()->with('success', 'Configurações do agente salvas com sucesso.');
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, badge_classes: string}>
     */
    private function specializations(): array
    {
        return collect(config('credflow.agent_specializations', []))
            ->map(fn (array $specialization, string $value) => array_merge(['value' => $value], $specialization))
            ->values()
            ->all();
    }
}
