<?php

namespace App\Console\Commands;

use App\Models\ConversationSession;
use App\Models\Lead;
use App\Services\WhatsApp\PhoneNumberValidator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Merges the duplicate conversations the 9th-digit mismatch created.
 *
 * Until {@see Lead::scopeForPhoneVariants} landed, an inbound webhook writing a BR mobile
 * as 12 digits and a CSV import or campaign mirror writing it as 13 produced two Lead rows
 * for one person — `leads_tenant_whatsapp_active_unique` compares strings and lets both
 * through. The operator then sees the same customer twice in /conversas, each half holding
 * part of the history, and the AI can be paused on one while answering on the other.
 *
 * The sibling command for the contacts side is {@see DedupeContactPhoneVariantsCommand}.
 * Run that one too: the two tables split independently.
 *
 * The merge is destructive. Losers are soft-deleted, not erased, so a bad run is
 * recoverable, but always read the --dry-run report before committing.
 */
class DedupeLeadPhoneVariantsCommand extends Command
{
    protected $signature = 'leads:dedupe-phone-variants
        {--tenant= : Restrict to a single tenant id}
        {--dry-run : Print the merges without writing}';

    protected $description = 'Merge leads duplicated across the BR 9th-digit phone spellings';

    /**
     * Everything that points at a lead. All of it has to follow the survivor, or the merge
     * hides half of a person's history behind a soft-deleted row.
     *
     * @var list<string>
     */
    private const LEAD_REFERENCES = [
        'agent_interaction_events',
        'ai_runs',
        'contact_list_entries',
        'conversation_sessions',
        'conversation_timeline_messages',
        'failed_interactions',
        'followup_messages',
        'service_tickets',
        'stress_test_cycles',
        'whatsapp_outbox_messages',
    ];

    /**
     * Fields the survivor takes from a loser only when it has none of its own.
     *
     * @var list<string>
     */
    private const FILL_IF_BLANK = [
        'nome',
        'cpf',
        'contact_id',
        'campaign_id',
        'assigned_user_id',
        'conversation_id',
        'agent_id',
        'whatsapp_instance_id',
        'evolution_instance',
    ];

    /**
     * Fields where the later of the two wins. A conversation window or a pause is a fact
     * about the person, so the longer-lived one binds the merged row: closing a window
     * that is genuinely open would block a reply that WhatsApp still allows.
     *
     * @var list<string>
     */
    private const KEEP_LATEST = [
        'last_interaction_at',
        'last_inbound_at',
        'service_window_expires_at',
        'free_entry_point_started_at',
        'free_entry_point_expires_at',
        'ai_paused_until',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenant = $this->option('tenant');

        $groups = $this->duplicateGroups($tenant === null ? null : (string) $tenant);

        if ($groups === []) {
            $this->info('No duplicated leads found.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d subscriber(s) hold more than one lead row.', count($groups)));

        $rows = [];
        $merged = 0;

        foreach ($groups as $key => $ids) {
            [$tenantId, $canonical] = explode('|', (string) $key, 2);

            /** @var Collection<int, Lead> $group */
            $group = Lead::withoutGlobalScopes()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get();

            $survivor = $this->pickSurvivor($group, $canonical);
            $losers = $group->reject(fn (Lead $lead): bool => $lead->id === $survivor->id);

            $rows[] = [
                $tenantId,
                $canonical,
                $survivor->id.' ('.$survivor->whatsapp.')',
                $losers->map(fn (Lead $lead): string => $lead->id.' ('.$lead->whatsapp.')')->implode(', '),
                (string) $this->messageCount($losers->modelKeys()),
            ];

            if ($dryRun) {
                continue;
            }

            $this->merge($survivor, $losers, $canonical);
            $merged++;
        }

        $this->table(
            ['Tenant', 'Canonical phone', 'Survivor', 'Merged away', 'Messages moving'],
            $rows,
        );

        if ($dryRun) {
            $this->info('[DRY RUN] Nothing was written.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Merged %d subscriber(s).', $merged));

        return self::SUCCESS;
    }

    /**
     * Lead ids grouped by tenant + canonical phone, keeping only the groups with a
     * duplicate in them.
     *
     * Streamed over three columns rather than hydrated: a tenant that ran a 50k campaign
     * fan-out has 50k leads, and loading them all as models to find the handful of
     * duplicates is how this kind of command runs the VPS out of memory.
     *
     * Soft-deleted rows are excluded by hand — withoutGlobalScopes() drops the
     * soft-delete scope along with the tenant one, and a lead merged away by an earlier
     * run must not come back as a duplicate of its own survivor.
     *
     * @return array<string, list<int>>
     */
    private function duplicateGroups(?string $tenant): array
    {
        $groups = [];

        Lead::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNotNull('whatsapp')
            ->when($tenant !== null, fn ($query) => $query->where('tenant_id', $tenant))
            ->orderBy('id')
            ->select(['id', 'tenant_id', 'whatsapp'])
            ->lazy()
            ->each(function (Lead $lead) use (&$groups): void {
                $canonical = PhoneNumberValidator::canonical($lead->whatsapp) ?? (string) $lead->whatsapp;
                $groups[$lead->tenant_id.'|'.$canonical][] = $lead->id;
            });

        return array_filter($groups, fn (array $ids): bool => count($ids) > 1);
    }

    /**
     * The row the others fold into: the well-formed spelling when one exists, otherwise the
     * oldest, which is the conversation the customer has been in the longest.
     *
     * @param  Collection<int, Lead>  $group
     */
    private function pickSurvivor(Collection $group, string $canonical): Lead
    {
        return $group->firstWhere('whatsapp', $canonical) ?? $group->first();
    }

    /**
     * @param  list<int>  $leadIds
     */
    private function messageCount(array $leadIds): int
    {
        return DB::table('conversation_timeline_messages')->whereIn('lead_id', $leadIds)->count();
    }

    /**
     * @param  Collection<int, Lead>  $losers
     */
    private function merge(Lead $survivor, Collection $losers, string $canonical): void
    {
        DB::transaction(function () use ($survivor, $losers, $canonical): void {
            $updates = [];
            $loserIds = $losers->modelKeys();

            foreach ($losers as $loser) {
                foreach (self::FILL_IF_BLANK as $field) {
                    if (blank($survivor->{$field}) && filled($loser->{$field})) {
                        $survivor->{$field} = $loser->{$field};
                        $updates[$field] = $loser->{$field};
                    }
                }

                foreach (self::KEEP_LATEST as $field) {
                    if ($loser->{$field} === null) {
                        continue;
                    }

                    if ($survivor->{$field} === null || $loser->{$field}->greaterThan($survivor->{$field})) {
                        $survivor->{$field} = $loser->{$field};
                        $updates[$field] = $loser->{$field};
                    }
                }

                if ((int) $loser->followup_count > (int) $survivor->followup_count) {
                    $updates['followup_count'] = (int) $loser->followup_count;
                    $survivor->followup_count = $updates['followup_count'];
                }
            }

            // Before the sessions move, or the partial unique index rejects the second open
            // one. Whichever atendimento saw the last message stays open; the others close
            // as abandoned, which is what they factually were once the split happened.
            $this->reconcileOpenSessions($survivor, $loserIds);

            foreach (self::LEAD_REFERENCES as $table) {
                DB::table($table)->whereIn('lead_id', $loserIds)->update(['lead_id' => $survivor->id]);
            }

            $this->renumberSessions($survivor);

            // Soft-deleted before the survivor is rewritten: the unique index is partial on
            // deleted_at IS NULL, so the losers have to leave it before the canonical
            // spelling can be claimed.
            foreach ($losers as $loser) {
                $loser->delete();
            }

            if ($survivor->whatsapp !== $canonical) {
                $updates['whatsapp'] = $canonical;
            }

            if ($updates !== []) {
                $survivor->updateQuietly($updates);
            }
        });
    }

    /**
     * Leave exactly one open session across the whole group.
     *
     * @param  list<int>  $loserIds
     */
    private function reconcileOpenSessions(Lead $survivor, array $loserIds): void
    {
        $open = ConversationSession::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('status', ConversationSession::STATUS_OPEN)
            ->whereIn('lead_id', [$survivor->id, ...$loserIds])
            ->orderByDesc('last_message_at')
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->get();

        foreach ($open->skip(1) as $session) {
            $session->updateQuietly([
                'status' => ConversationSession::STATUS_CLOSED,
                'outcome' => ConversationSession::OUTCOME_ABANDONED,
                'closed_at' => $session->last_message_at ?? $session->opened_at ?? now(),
            ]);
        }
    }

    /**
     * Renumber the survivor's atendimentos chronologically.
     *
     * Both leads numbered their own sessions from 1, so a merge without this leaves the
     * customer with two "Atendimento #1" and no way to tell which came first.
     */
    private function renumberSessions(Lead $survivor): void
    {
        $sessions = ConversationSession::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('lead_id', $survivor->id)
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();

        foreach ($sessions->values() as $index => $session) {
            $number = $index + 1;

            if ((int) $session->number !== $number) {
                $session->updateQuietly(['number' => $number]);
            }
        }
    }
}
