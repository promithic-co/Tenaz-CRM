<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\UpdateAgentModelRequest;
use App\Models\Agent;
use App\Models\AgentConfig;
use Illuminate\Http\RedirectResponse;

class BackofficeAgentModelController extends Controller
{
    /**
     * Writes the LLM choice of a single agent. Cross-tenant isolation comes from
     * route-model binding: the tenant global scope makes an agent of another
     * company 404 while a company is active.
     *
     * The 300s `agent_config_id_{id}` cache is busted by AgentConfig's `saved`
     * hook, so the next turn already runs on the new model.
     */
    public function update(UpdateAgentModelRequest $request, Agent $agent): RedirectResponse
    {
        /** Fetched by agent_id alone — the agent is already tenant-checked, and a legacy row with a null tenant_id would otherwise be duplicated. */
        $config = AgentConfig::query()
            ->withoutGlobalScope('tenant')
            ->firstOrNew(['agent_id' => $agent->id]);

        if (! $config->exists) {
            /** Same seed AgentConfigController uses when materialising a missing row. */
            $config->fill([
                'tenant_id' => $agent->tenant_id,
                'agent_name' => $agent->name,
                'agent_niche' => 'generic',
            ]);
        }

        $config->fill($request->validated())->save();

        return back()->with('success', 'Modelo LLM do agente atualizado.');
    }
}
