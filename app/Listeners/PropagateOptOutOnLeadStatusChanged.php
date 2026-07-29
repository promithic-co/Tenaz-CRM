<?php

namespace App\Listeners;

use App\Events\LeadStatusChanged;
use App\Models\Lead;
use App\Services\OptOutConsentPropagator;
use Illuminate\Support\Facades\Log;

/**
 * Writes the consent record when a lead reaches `optou_sair`.
 *
 * The status alone never suppressed anything: the campaign send gate reads
 * Contact::opt_in_status and ContactListEntry::opt_in_status, and no WhatsApp path wrote
 * them. See {@see OptOutConsentPropagator} for the matching rules and why they are shared.
 *
 * Deliberately one-way. Moving a lead back out of `optou_sair` does not restore consent:
 * revocation is the person's decision, and only an explicit opt-in may undo it.
 */
class PropagateOptOutOnLeadStatusChanged
{
    public function __construct(private readonly OptOutConsentPropagator $propagator) {}

    public function handle(LeadStatusChanged $event): void
    {
        if ($event->newStatus !== 'optou_sair') {
            return;
        }

        $lead = Lead::withoutGlobalScopes()->find($event->leadId);

        if ($lead === null) {
            return;
        }

        $changed = $this->propagator->propagate($lead);

        if ($changed['contacts'] === 0 && $changed['entries'] === 0) {
            return;
        }

        Log::info('lead_opt_out.consent_propagated', [
            'lead_id' => $lead->id,
            'tenant_id' => $event->tenantId,
            'contacts' => $changed['contacts'],
            'entries' => $changed['entries'],
        ]);
    }
}
