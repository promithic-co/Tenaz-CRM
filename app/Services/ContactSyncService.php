<?php

namespace App\Services;

use App\Console\Commands\DedupeContactPhoneVariantsCommand;
use App\Models\Contact;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Services\WhatsApp\PhoneNumberValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Centralizes canonical Contact creation/linking across all lead and list entry
 * entry points. Always resolves by tenant_id + normalized phone, then links the
 * supplied Lead or ContactListEntry to that canonical contact.
 */
class ContactSyncService
{
    public function __construct(private readonly ContactExtraDataService $extraData) {}

    /**
     * Resolve or create a canonical contact for a tenant + phone, applying optional
     * profile updates (name, email, cpf, extra_data) only when missing/non-empty.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function resolveContact(
        string $tenantId,
        ?string $rawPhone,
        array $attrs = [],
        string $source = Contact::SOURCE_MANUAL,
    ): ?Contact {
        $phone = $this->normalizePhone($rawPhone);
        if ($phone === null) {
            return null;
        }

        $canonical = PhoneNumberValidator::canonical($phone) ?? $phone;

        // Locked per subscriber, not per digit form. The two 9th-digit spellings of the
        // same mobile arrive concurrently — webhook and CSV import — and a lock keyed on
        // the raw form lets both through to create a contact each.
        $lockKey = "contact_sync_{$tenantId}_{$canonical}";

        return Cache::lock($lockKey, 8)->block(5, function () use ($tenantId, $phone, $canonical, $attrs, $source): Contact {
            $contact = $this->findAcrossPhoneVariants($tenantId, $phone, $canonical);

            if (! $contact) {
                return Contact::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'phone' => $canonical,
                    'name' => $attrs['name'] ?? null,
                    'email' => $attrs['email'] ?? null,
                    'cpf' => $attrs['cpf'] ?? null,
                    'extra_data' => $attrs['extra_data'] ?? null,
                    'source' => $source,
                    'opt_in_status' => $attrs['opt_in_status'] ?? Contact::OPT_PENDING,
                    'opt_in_at' => $attrs['opt_in_at'] ?? null,
                    'last_seen_at' => $attrs['last_seen_at'] ?? null,
                ]);
            }

            if ($contact->trashed()) {
                $contact->restore();
            }

            $updates = [];
            foreach (['name', 'email', 'cpf'] as $field) {
                if (empty($contact->{$field}) && ! empty($attrs[$field] ?? null)) {
                    $updates[$field] = $attrs[$field];
                }
            }

            if (isset($attrs['last_seen_at'])) {
                $updates['last_seen_at'] = $attrs['last_seen_at'];
            }

            if ($updates !== []) {
                $contact->update($updates);
            }

            if (! empty($attrs['extra_data'] ?? null) && is_array($attrs['extra_data'])) {
                $this->extraData->merge($contact, $attrs['extra_data']);
            }

            return $contact;
        });
    }

    /**
     * The existing contact for this subscriber under any 9th-digit spelling.
     *
     * Matching the exact string alone is what minted a second Contact per person: the CSV
     * import stores a BR mobile as 13 digits and the inbound webhook as 12, so the reply to
     * a campaign never resolved to the imported contact. The duplicate then had to be
     * opted out, tagged and reported on twice, and consistently only one of the two was.
     *
     * Where history already holds both spellings the exact match wins, so leads and list
     * entries keep pointing at the contact they were linked to; consolidating them is
     * {@see DedupeContactPhoneVariantsCommand}'s job, which merges the
     * profile data instead of silently relinking.
     */
    private function findAcrossPhoneVariants(string $tenantId, string $phone, string $canonical): ?Contact
    {
        $matches = Contact::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->whereIn('phone', PhoneNumberValidator::variants($phone))
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            return null;
        }

        return $matches->firstWhere('phone', $phone)
            ?? $matches->firstWhere('phone', $canonical)
            ?? $matches->first();
    }

    /**
     * Sync a Lead with a canonical Contact. Returns the resolved contact (or null).
     */
    public function syncFromLead(Lead $lead, string $source = Contact::SOURCE_LEAD_SYNC): ?Contact
    {
        if (empty($lead->whatsapp) || empty($lead->tenant_id)) {
            return null;
        }

        if ($lead->contact_id !== null) {
            /** @var Contact|null $existing */
            $existing = Contact::withoutGlobalScopes()
                ->whereKey($lead->contact_id)
                ->where('tenant_id', (string) $lead->tenant_id)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $contact = $this->resolveContact(
            tenantId: (string) $lead->tenant_id,
            rawPhone: (string) $lead->whatsapp,
            attrs: [
                'name' => $lead->nome,
                'cpf' => $lead->cpf,
                'last_seen_at' => $lead->last_inbound_at ?? $lead->last_interaction_at,
            ],
            source: $source,
        );

        if ($contact && (int) $lead->contact_id !== (int) $contact->id) {
            // Use a raw update to avoid retriggering global scopes / model events
            // for a metadata-only link change.
            DB::table('leads')->where('id', $lead->id)->update(['contact_id' => $contact->id]);
            $lead->contact_id = $contact->id;
        }

        return $contact;
    }

    /**
     * Sync a ContactListEntry with a canonical Contact and link contact_id.
     */
    public function syncFromEntry(ContactListEntry $entry, string $source = Contact::SOURCE_CSV_IMPORT): ?Contact
    {
        $list = $entry->contactList;
        if (! $list) {
            return null;
        }

        $contact = $this->resolveContact(
            tenantId: (string) $list->tenant_id,
            rawPhone: (string) $entry->phone,
            attrs: [
                'name' => $entry->name,
                'extra_data' => $entry->extra_data,
                'opt_in_status' => $entry->opt_in_status,
                'opt_in_at' => $entry->opt_in_at,
            ],
            source: $source,
        );

        if ($contact && (int) $entry->contact_id !== (int) $contact->id) {
            DB::table('contact_list_entries')->where('id', $entry->id)->update(['contact_id' => $contact->id]);
            $entry->contact_id = $contact->id;
        }

        return $contact;
    }

    /**
     * The single canonical phone normalizer for the whole contacts domain — lead
     * sync, CSV import, and list-entry sync all resolve through this method so a
     * person always maps to the same contact key regardless of entry point.
     *
     * Permissive by design: tries the E.164 validator first, then falls back to
     * digits-only so foreign and imperfect numbers still resolve to a canonical
     * contact. Malformed numbers are filtered later at send time by
     * {@see PhoneNumberValidator}, which guards the Meta reputation tier.
     */
    public function normalizePhone(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $normalized = PhoneNumberValidator::normalize($raw);
        if ($normalized !== null) {
            return $normalized;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits !== '' ? $digits : null;
    }
}
