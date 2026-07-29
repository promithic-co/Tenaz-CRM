<?php

namespace App\Services;

use App\Console\Commands\BackfillOptOutConsentCommand;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Services\WhatsApp\PhoneNumberValidator;

/**
 * Turns a lead's `optou_sair` status into the consent record the send gate actually reads.
 *
 * `optou_sair` is a sales-funnel status. The campaign send gate evaluates a different pair
 * of columns — Contact::opt_in_status and ContactListEntry::opt_in_status
 * ({@see SendCampaignMessageJob}) — and nothing on the WhatsApp path ever wrote
 * them; the URA's DTMF handler was the only caller of markOptedOut(). A recipient who
 * answered a template with "bloquear" was shown as OPT-OUT in the inbox and still received
 * the next send, which is both a consent violation and a block report against the number.
 *
 * Phones are matched across every 9th-digit form: the lead is created from the inbound
 * webhook (BR mobile without the 9) while the list entry comes from a CSV import (with it).
 * Every matching row is updated rather than the first, because that same mismatch has been
 * minting a second Contact for the same person — suppressing only one would leave the other
 * free to be sent to.
 *
 * Lives here rather than inside the listener so the historical repair
 * ({@see BackfillOptOutConsentCommand}) resolves through the identical
 * matching rules. Splitting them is what produced this class of bug to begin with.
 */
class OptOutConsentPropagator
{
    /**
     * Suppress every consent row belonging to this lead's subscriber.
     *
     * Idempotent: rows already opted out are skipped, so opt_out_at keeps the moment
     * consent was first revoked.
     *
     * @return array{contacts: int, entries: int} rows changed by this call
     */
    public function propagate(Lead $lead): array
    {
        $variants = PhoneNumberValidator::variants((string) $lead->whatsapp);

        if ($variants === []) {
            return ['contacts' => 0, 'entries' => 0];
        }

        $tenantId = (string) $lead->tenant_id;

        $contacts = Contact::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('phone', $variants)
            ->where(fn ($query) => $query->where('opt_in_status', '!=', Contact::OPT_OUT)
                ->orWhereNull('opt_in_status'))
            ->get();

        foreach ($contacts as $contact) {
            $contact->markOptedOut();
        }

        // ContactListEntry carries no tenant column of its own, so the tenant boundary
        // is enforced through the owning list.
        $entries = ContactListEntry::whereIn('phone', $variants)
            ->whereIn('contact_list_id', ContactList::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->select('id'))
            ->where(fn ($query) => $query->where('opt_in_status', '!=', 'opted_out')
                ->orWhereNull('opt_in_status'))
            ->get();

        foreach ($entries as $entry) {
            $entry->markOptedOut();
        }

        return ['contacts' => $contacts->count(), 'entries' => $entries->count()];
    }
}
