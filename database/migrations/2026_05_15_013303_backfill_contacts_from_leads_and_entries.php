<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill canonical contacts.
 *
 * Order:
 *   1. From production leads first (is_sandbox = 0). Sandbox/test leads are excluded so the
 *      canonical contact table reflects real CRM identities only.
 *   2. From contact_list_entries that didn't match an existing lead.
 *
 * Matching key: tenant_id + normalized phone (digits only, prefix 55 added to short BR numbers).
 * Idempotent: re-running skips rows that already have contact_id set.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->toDateTimeString();

        // 1) Production leads first.
        $leads = DB::table('leads')
            ->whereNull('contact_id')
            ->where('is_sandbox', false)
            ->whereNotNull('whatsapp')
            ->select('id', 'tenant_id', 'whatsapp', 'nome', 'cpf', 'last_inbound_at', 'last_interaction_at')
            ->cursor();

        foreach ($leads as $lead) {
            $phone = $this->normalizePhone($lead->whatsapp);
            if ($phone === null) {
                continue;
            }

            $existing = DB::table('contacts')
                ->where('tenant_id', $lead->tenant_id)
                ->where('phone', $phone)
                ->first();

            if ($existing) {
                $contactId = $existing->id;
                $updates = [];
                if (empty($existing->name) && ! empty($lead->nome)) {
                    $updates['name'] = $lead->nome;
                }
                if (empty($existing->cpf) && ! empty($lead->cpf)) {
                    $updates['cpf'] = $lead->cpf;
                }
                if ($updates !== []) {
                    $updates['updated_at'] = $now;
                    DB::table('contacts')->where('id', $contactId)->update($updates);
                }
            } else {
                $contactId = DB::table('contacts')->insertGetId([
                    'tenant_id' => $lead->tenant_id,
                    'phone' => $phone,
                    'name' => $lead->nome,
                    'cpf' => $lead->cpf,
                    'source' => 'lead_sync',
                    'opt_in_status' => 'pending',
                    'last_seen_at' => $lead->last_inbound_at ?: $lead->last_interaction_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('leads')->where('id', $lead->id)->update(['contact_id' => $contactId]);
        }

        // 2) Contact list entries (use list's tenant_id).
        $entries = DB::table('contact_list_entries')
            ->whereNull('contact_list_entries.contact_id')
            ->join('contact_lists', 'contact_lists.id', '=', 'contact_list_entries.contact_list_id')
            ->select(
                'contact_list_entries.id',
                'contact_list_entries.phone',
                'contact_list_entries.name',
                'contact_list_entries.opt_in_status',
                'contact_list_entries.opt_in_at',
                'contact_list_entries.extra_data',
                'contact_lists.tenant_id',
            )
            ->cursor();

        foreach ($entries as $entry) {
            $phone = $this->normalizePhone($entry->phone);
            if ($phone === null) {
                continue;
            }

            $existing = DB::table('contacts')
                ->where('tenant_id', $entry->tenant_id)
                ->where('phone', $phone)
                ->first();

            if ($existing) {
                $contactId = $existing->id;
                $updates = [];
                if (empty($existing->name) && ! empty($entry->name)) {
                    $updates['name'] = $entry->name;
                }
                if ($updates !== []) {
                    $updates['updated_at'] = $now;
                    DB::table('contacts')->where('id', $contactId)->update($updates);
                }
            } else {
                $contactId = DB::table('contacts')->insertGetId([
                    'tenant_id' => $entry->tenant_id,
                    'phone' => $phone,
                    'name' => $entry->name,
                    'source' => 'csv_import',
                    'opt_in_status' => $entry->opt_in_status ?? 'pending',
                    'opt_in_at' => $entry->opt_in_at,
                    'extra_data' => $entry->extra_data,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('contact_list_entries')->where('id', $entry->id)->update(['contact_id' => $contactId]);
        }
    }

    public function down(): void
    {
        DB::table('leads')->update(['contact_id' => null]);
        DB::table('contact_list_entries')->update(['contact_id' => null]);
        DB::table('contacts')->whereIn('source', ['lead_sync', 'csv_import'])->delete();
    }

    private function normalizePhone(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Add BR country code if missing (10–11 local digits).
        if (strlen($digits) <= 11) {
            $digits = '55'.$digits;
        }

        return $digits;
    }
};
