<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(): Response
    {
        // BelongsToTenant global scope on Campaign handles tenant filtering automatically
        $campaigns = Campaign::query()
            ->with(['whatsappInstance', 'whatsappTemplate', 'contactList'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('campanhas/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(Request $request): Response
    {
        $defaults = [
            'contact_list_id' => $request->integer('contact_list_id') ?: null,
            'whatsapp_instance_id' => $request->integer('whatsapp_instance_id') ?: null,
        ];

        // BelongsToTenant global scope on each model handles tenant filtering automatically
        return Inertia::render('campanhas/Create', [
            'contactLists' => ContactList::query()
                ->get(['id', 'name', 'is_dynamic', 'entries_count', 'last_resolved_count', 'last_resolved_at', 'filters_json']),
            'templates' => WhatsappTemplate::query()
                ->where('status', 'APPROVED')
                ->with('whatsappInstance')
                ->get(['id', 'name', 'kind', 'element_name', 'body', 'variables_count', 'whatsapp_instance_id']),
            'instances' => WhatsappInstance::query()->get(['id', 'name', 'display_name', 'provider']),
            'defaults' => $defaults,
        ]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $campaign = Campaign::create([
            'tenant_id' => auth()->user()->tenantId,
            'name' => $validated['name'],
            'whatsapp_instance_id' => $validated['whatsapp_instance_id'],
            'contact_list_id' => $validated['contact_list_id'],
            'whatsapp_template_id' => $validated['whatsapp_template_id'],
            'template_params_mapping' => $validated['template_params_mapping'] ?? null,
            'daily_limit' => $validated['daily_limit'] ?? 1000,
            'delay_between_ms' => $validated['delay_between_ms'] ?? 1000,
            'error_threshold_percent' => $validated['error_threshold_percent'] ?? 10,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'status' => isset($validated['scheduled_at']) ? 'scheduled' : 'draft',
        ]);

        return redirect()->route('campanhas.show', $campaign)
            ->with('success', 'Campanha criada com sucesso.');
    }

    public function show(Request $request, Campaign $campanha): Response
    {
        $this->authorize('view', $campanha);

        $campanha->load([
            'contactList:id,name',
            'whatsappTemplate:id,name,body,variables_count',
            'whatsappInstance:id,name,display_name',
        ]);

        $statusFilter = $request->input('status');

        $messagesQuery = $campanha->messages()->with(['contactListEntry:id,name,phone']);

        if ($statusFilter) {
            $messagesQuery->where('status', $statusFilter);
        }

        return Inertia::render('campanhas/Show', [
            'campaign' => $campanha,
            'messages' => $messagesQuery->orderByDesc('sent_at')->paginate(25),
            'repliedCount' => Lead::where('campaign_id', $campanha->id)->count(),
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campanha): RedirectResponse
    {
        $this->authorize('update', $campanha);

        if (! in_array($campanha->status, ['draft', 'scheduled'])) {
            return back()->withErrors(['campaign' => 'Apenas campanhas em rascunho ou agendadas podem ser editadas.']);
        }

        $validated = $request->validated();

        if (isset($validated['scheduled_at']) && ! isset($validated['status'])) {
            $validated['status'] = 'scheduled';
        }

        $campanha->update($validated);

        return back()->with('success', 'Campanha atualizada.');
    }

    public function destroy(Campaign $campanha): RedirectResponse
    {
        $this->authorize('delete', $campanha);

        if (in_array($campanha->status, ['sending', 'paused'])) {
            return back()->withErrors(['campaign' => 'Não é possível excluir uma campanha em andamento.']);
        }

        $campanha->delete();

        return redirect()->route('campanhas.index')
            ->with('success', 'Campanha removida.');
    }

    public function start(Campaign $campanha, CampaignService $service): RedirectResponse
    {
        $this->authorize('update', $campanha);

        try {
            $service->start($campanha);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('success', 'Campanha iniciada.');
    }

    public function pause(Campaign $campanha, CampaignService $service): RedirectResponse
    {
        $this->authorize('update', $campanha);

        try {
            $service->pause($campanha);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('success', 'Campanha pausada.');
    }

    public function resume(Campaign $campanha, CampaignService $service): RedirectResponse
    {
        $this->authorize('update', $campanha);

        try {
            $service->resume($campanha);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('success', 'Campanha retomada.');
    }
}
