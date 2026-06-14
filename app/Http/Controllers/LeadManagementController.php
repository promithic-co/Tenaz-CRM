<?php

namespace App\Http\Controllers;

use App\Actions\BulkLeadActions\ApplyBulkLeadAction;
use App\Actions\CreateOrRestoreLeadAction;
use App\Http\Requests\BulkLeadActionRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\AgentInteractionEventService;
use App\Services\ContactSyncService;
use App\Services\PauseService;
use Illuminate\Http\RedirectResponse;

class LeadManagementController extends Controller
{
    public function __construct(
        private readonly PauseService $pause,
        private readonly AgentInteractionEventService $events,
        private readonly CreateOrRestoreLeadAction $createOrRestoreLead,
        private readonly ApplyBulkLeadAction $applyBulkLeadAction,
        private readonly ContactSyncService $contactSync,
    ) {}

    /**
     * Create or restore a lead manually. If an active lead already exists for the
     * tenant + WhatsApp pair, redirect to it. If a soft-deleted one exists, restore
     * and patch core fields. Otherwise create a fresh row.
     */
    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $user = $request->user();

        $result = $this->createOrRestoreLead->execute(
            tenantId: (string) $user->tenantId,
            userId: $user->id,
            data: $request->validated(),
        );

        if ($result['existed']) {
            return redirect()
                ->route('conversas.show', $result['lead'])
                ->with('flash', 'Lead já existe para este WhatsApp.');
        }

        return redirect()
            ->route('conversas.show', $result['lead'])
            ->with('flash', 'Contato criado com sucesso.');
    }

    /**
     * Soft delete a lead. Preserves timeline, follow-up history, and audit trail so
     * the row can be restored later via the manual-create flow.
     */
    public function destroy(Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        $interactionId = $this->events->newInteractionId();

        $this->events->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'lead_deleted_manual',
            eventSource: 'lead_management_controller',
            payload: [
                'user_id' => auth()->id(),
                'followup_status_before' => $lead->followup_status,
            ],
        );

        // Stop any active automation so a zombie job doesn't try to reply after delete.
        if (! empty($lead->whatsapp)) {
            $this->pause->pause((string) $lead->whatsapp, (string) $lead->tenant_id);
        }

        $lead->update([
            'followup_status' => 'inactive',
            'ai_mode' => Lead::AI_MODE_MANUAL,
        ]);

        $lead->delete();

        return redirect()
            ->route('conversas.index')
            ->with('flash', 'Lead removido.');
    }

    /**
     * Apply the same operator action to a batch of leads. Authorizes per lead and
     * collects an aggregate success/skip count for the flash response.
     */
    public function bulkAction(BulkLeadActionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $action = (string) $request->validated('action');
        /** @var array<int, int> $ids */
        $ids = $request->validated('lead_ids');

        $leads = Lead::query()
            ->whereIn('id', $ids)
            ->withTrashed()
            ->get();

        $applied = 0;
        $skipped = 0;

        foreach ($leads as $lead) {
            if (! $user->can('update', $lead) && ! $user->can('delete', $lead)) {
                $skipped++;

                continue;
            }

            try {
                $this->applyBulkLeadAction->execute($lead, $action, $user->id);
                $applied++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        $missing = count($ids) - $leads->count();
        $skipped += max(0, $missing);

        return back()->with('flash', "Ação aplicada a {$applied} leads. {$skipped} ignorados.");
    }

    /**
     * Build (or reuse) a one-contact list for a lead and redirect to the campaign
     * creation page with the contact list and instance preselected. Lets an operator
     * launch an outbound template message to a single lead via the standard campaign
     * pipeline — no free-form direct send.
     */
    public function prepareCampaign(Lead $lead): RedirectResponse
    {
        $this->authorize('view', $lead);

        $user = auth()->user();
        if (! $user?->isOwnerOrAdmin()) {
            abort(403, 'Apenas owner ou administrador pode iniciar campanha por aqui.');
        }

        $tenantId = (string) $lead->tenant_id;

        $list = ContactList::query()
            ->where('tenant_id', $tenantId)
            ->where('source', 'individual')
            ->whereHas('entries', fn ($q) => $q->where('lead_id', $lead->id))
            ->first();

        if (! $list) {
            $list = ContactList::create([
                'tenant_id' => $tenantId,
                'name' => 'Individual: '.($lead->nome ?: $lead->whatsapp),
                'description' => 'Lista gerada automaticamente para envio individual.',
                'source' => 'individual',
                'entries_count' => 0,
            ]);
        }

        ContactListEntry::firstOrCreate(
            ['contact_list_id' => $list->id, 'phone' => $lead->whatsapp],
            [
                'name' => $lead->nome,
                'lead_id' => $lead->id,
                'opt_in_status' => 'opted_in',
                'opt_in_at' => now(),
            ]
        );

        $list->refreshEntriesCount();

        $instance = $lead->evolution_instance
            ? WhatsappInstance::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $lead->evolution_instance)
                ->first()
            : null;

        $query = ['contact_list_id' => $list->id];
        if ($instance) {
            $query['whatsapp_instance_id'] = $instance->id;
        }

        return redirect()->route('campanhas.create', $query);
    }

    /**
     * Promote a lead to the canonical contact base. Resolves (or creates) the
     * tenant + phone Contact and links the lead's `contact_id` to it. Idempotent:
     * if the lead is already linked, redirects to the existing contact.
     */
    public function addToContacts(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $contact = $this->contactSync->syncFromLead($lead);

        if (! $contact) {
            return back()->with('flash', 'Não foi possível adicionar à base: telefone inválido.');
        }

        return redirect()
            ->route('contatos.show', $contact)
            ->with('flash', 'Contato adicionado à base.');
    }
}
