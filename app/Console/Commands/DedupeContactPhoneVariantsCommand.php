<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\ContactSyncService;
use App\Services\WhatsApp\PhoneNumberValidator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Merges the duplicate contacts the 9th-digit mismatch created.
 *
 * Until {@see ContactSyncService} resolved across spellings, a CSV import writing a BR
 * mobile as 13 digits and an inbound webhook writing it as 12 produced two Contact rows for
 * one person — the `contacts_tenant_phone_unique` index sees two different strings and lets
 * both through. Everything keyed on the contact then splits in half: opt-out suppresses one
 * row while the other stays sendable, and the person shows up twice in every list.
 *
 * The merge is destructive. Losers are soft-deleted, not erased, so a bad run is
 * recoverable, but always read the --dry-run report before committing.
 */
class DedupeContactPhoneVariantsCommand extends Command
{
    protected $signature = 'contacts:dedupe-phone-variants
        {--tenant= : Restrict to a single tenant id}
        {--dry-run : Print the merges without writing}';

    protected $description = 'Merge contacts duplicated across the BR 9th-digit phone spellings';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenant = $this->option('tenant');

        // withoutGlobalScopes() drops the soft-delete scope along with the tenant one, so
        // deleted_at is filtered by hand: a contact merged away by an earlier run must not
        // be dragged back in as a duplicate of its own survivor.
        $groups = Contact::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->when($tenant !== null, fn ($query) => $query->where('tenant_id', (string) $tenant))
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Contact $contact): string => $contact->tenant_id.'|'.(PhoneNumberValidator::canonical($contact->phone) ?? $contact->phone))
            ->filter(fn (Collection $group): bool => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicated contacts found.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d subscriber(s) hold more than one contact row.', $groups->count()));

        $rows = [];
        $merged = 0;

        foreach ($groups as $key => $group) {
            $canonical = explode('|', (string) $key, 2)[1];
            $survivor = $this->pickSurvivor($group, $canonical);
            $losers = $group->reject(fn (Contact $contact): bool => $contact->id === $survivor->id);

            $rows[] = [
                $survivor->tenant_id,
                $canonical,
                $survivor->id.' ('.$survivor->phone.')',
                $losers->map(fn (Contact $c): string => $c->id.' ('.$c->phone.')')->implode(', '),
            ];

            if ($dryRun) {
                continue;
            }

            $this->merge($survivor, $losers);
            $merged++;
        }

        $this->table(['Tenant', 'Canonical phone', 'Survivor', 'Merged away'], $rows);

        if ($dryRun) {
            $this->info('[DRY RUN] Nothing was written.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Merged %d subscriber(s).', $merged));

        return self::SUCCESS;
    }

    /**
     * The row the others fold into: the well-formed spelling when one exists, otherwise the
     * oldest, which is whatever the most leads and list entries already point at.
     *
     * @param  Collection<int, Contact>  $group
     */
    private function pickSurvivor(Collection $group, string $canonical): Contact
    {
        return $group->firstWhere('phone', $canonical) ?? $group->first();
    }

    /**
     * @param  Collection<int, Contact>  $losers
     */
    private function merge(Contact $survivor, Collection $losers): void
    {
        DB::transaction(function () use ($survivor, $losers): void {
            $updates = [];

            foreach ($losers as $loser) {
                // Blank fields on the survivor take the loser's value; a name captured by
                // the webhook is worth keeping when the import row had none.
                foreach (['name', 'email', 'cpf'] as $field) {
                    if (empty($survivor->{$field}) && ! empty($loser->{$field})) {
                        $survivor->{$field} = $loser->{$field};
                        $updates[$field] = $loser->{$field};
                    }
                }

                if (is_array($loser->extra_data) && $loser->extra_data !== []) {
                    $updates['extra_data'] = array_merge($loser->extra_data, $survivor->extra_data ?? []);
                    $survivor->extra_data = $updates['extra_data'];
                }

                if ($loser->last_seen_at !== null
                    && ($survivor->last_seen_at === null || $loser->last_seen_at->gt($survivor->last_seen_at))) {
                    $updates['last_seen_at'] = $loser->last_seen_at;
                    $survivor->last_seen_at = $loser->last_seen_at;
                }

                // Consent is the one field where the loser can override a filled survivor:
                // an opt-out anywhere in the group binds the whole subscriber. Merging the
                // other way would hand a person who asked to be blocked back to the sender.
                if ($loser->opt_in_status === Contact::OPT_OUT && $survivor->opt_in_status !== Contact::OPT_OUT) {
                    $updates['opt_in_status'] = Contact::OPT_OUT;
                    $updates['opt_out_at'] = $loser->opt_out_at ?? now();
                    $survivor->opt_in_status = Contact::OPT_OUT;
                }

                DB::table('leads')->where('contact_id', $loser->id)->update(['contact_id' => $survivor->id]);
                DB::table('contact_list_entries')->where('contact_id', $loser->id)->update(['contact_id' => $survivor->id]);
            }

            if ($updates !== []) {
                $survivor->update($updates);
            }

            foreach ($losers as $loser) {
                $loser->delete();
            }
        });
    }
}
