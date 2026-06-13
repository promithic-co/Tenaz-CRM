<?php

namespace App\Http\Controllers;

use App\Actions\CreateAgentAction;
use App\Actions\ReassignAgentInstanceAction;
use App\Http\Requests\AssignAgentRequest;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentBasicsRequest;
use App\Http\Requests\UpdateAgentInstanceRequest;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Models\WhatsappInstance;
use App\Services\AgentTemplateService;
use App\Support\RoleScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AgentsController extends Controller
{
    public function index(): Response
    {
        $agents = RoleScope::applyOwnerScope(
            Agent::query()
                ->with(['whatsappInstance:id,agent_id,name,display_name,phone_number', 'config:agent_id,agent_name,agent_model,agent_provider,agent_niche,template_slug'])
                ->withCount([
                    'leads as leads_count' => fn ($q) => $q->production(),
                    'leads as active_conversations' => fn ($q) => $q->production()->whereIn('status', ['novo', 'qualificado', 'escalado']),
                    'leads as converted_count' => fn ($q) => $q->production()->where('status', 'convertido'),
                ])
        )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $agents = AgentResource::collection($agents)->resolve();

        $availableInstances = RoleScope::applyOwnerScope(
            WhatsappInstance::query()->whereNull('agent_id')
        )
            ->orderBy('created_at')
            ->get(['id', 'name', 'display_name', 'phone_number'])
            ->map(fn (WhatsappInstance $instance) => [
                'id' => $instance->id,
                'name' => $instance->name,
                'display_name' => $instance->display_name,
                'phone_number' => $instance->phone_number,
            ]);

        $archivedAgents = RoleScope::applyOwnerScope(Agent::onlyTrashed())
            ->orderByDesc('deleted_at')
            ->get(['id', 'name', 'description', 'deleted_at'])
            ->map(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'description' => $agent->description,
                'deleted_at' => $agent->deleted_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('agentes/Index', [
            'agents' => $agents,
            'archived_agents' => $archivedAgents,
            'available_instances' => $availableInstances,
            'flash' => session('success'),
        ]);
    }

    public function create(): Response
    {
        $instances = WhatsappInstance::query()
            ->where('user_id', auth()->id())
            ->whereNull('agent_id')
            ->orderBy('created_at')
            ->get(['id', 'name', 'display_name', 'phone_number'])
            ->map(fn (WhatsappInstance $instance) => [
                'id' => $instance->id,
                'name' => $instance->name,
                'display_name' => $instance->display_name,
                'phone_number' => $instance->phone_number,
            ]);

        return Inertia::render('agentes/Create', [
            'instances' => $instances,
            'templates' => app(AgentTemplateService::class)->all(),
            'default_template' => config('agent_templates.default'),
            'specializations' => $this->specializations(),
            'default_specialization' => 'inss',
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $agent = app(CreateAgentAction::class)->execute(
            userId: auth()->id(),
            tenantId: auth()->user()->tenantId,
            name: trim((string) $request->validated('name')),
            templateSlug: $request->validated('template_slug'),
            companyName: $request->validated('company_name'),
            agentNiche: $request->validated('agent_niche'),
            description: $request->validated('description'),
            whatsappInstanceId: $request->validated('whatsapp_instance_id'),
        );

        return redirect()->route('agentes.config', $agent)->with('success', 'Agente criado com sucesso.');
    }

    public function update(UpdateAgentBasicsRequest $request, Agent $agent): RedirectResponse
    {
        $agent->update([
            'name' => trim((string) $request->validated('name')),
            'description' => $request->validated('description'),
        ]);

        return back()->with('success', 'Agente atualizado com sucesso.');
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $this->authorize('delete', $agent);

        // Capture instance name BEFORE unlinking — query builder update() skips model events
        $instanceName = $agent->whatsappInstance?->name;

        // Unlink WhatsApp instance before archiving
        WhatsappInstance::query()
            ->where('agent_id', $agent->id)
            ->update(['agent_id' => null]);

        // Invalidate routing cache using the name captured before the unlink
        if ($instanceName) {
            Cache::forget("agent_context_instance_{$instanceName}");
        }

        $agent->delete();

        return redirect()->route('agentes.index')->with('success', 'Agente arquivado com sucesso.');
    }

    public function restore(int $agentId): RedirectResponse
    {
        $agent = Agent::withTrashed()
            ->where('id', $agentId)
            ->where('tenant_id', auth()->user()->tenantId)
            ->firstOrFail();

        $agent->restore();

        return redirect()->route('agentes.index')->with('success', 'Agente restaurado com sucesso.');
    }

    public function toggleActive(Agent $agent): RedirectResponse
    {
        $this->authorize('manage', $agent);

        // Reject activation when no instance is linked — an unlinked agent must never process work (T-60-06, D-14)
        if (! $agent->is_active && $agent->whatsappInstance()->doesntExist()) {
            return back()->withErrors(['toggle' => 'Não é possível ativar um agente sem instância de WhatsApp vinculada.']);
        }

        $agent->update(['is_active' => ! $agent->is_active]);

        $status = $agent->is_active ? 'ativado' : 'pausado';

        return back()->with('success', "Agente {$status} com sucesso.");
    }

    public function updateInstance(UpdateAgentInstanceRequest $request, Agent $agent, ReassignAgentInstanceAction $reassign): RedirectResponse
    {
        $this->authorize('manage', $agent);

        $reassign->execute(
            $agent,
            auth()->id(),
            auth()->user()->tenantId,
            $request->validated('whatsapp_instance_id'),
        );

        return redirect()->route('agentes.index')->with('success', 'Instância atualizada com sucesso.');
    }

    /**
     * Assign (or unassign) an agent to a specific user in the same tenant.
     * Owner/Administrator only (enforced by AssignAgentRequest::authorize).
     */
    public function assign(AssignAgentRequest $request, Agent $agent): RedirectResponse
    {
        $agent->update(['user_id' => $request->validated('user_id') ?? null]);

        return back()->with('success', 'Agente atribuído.');
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
