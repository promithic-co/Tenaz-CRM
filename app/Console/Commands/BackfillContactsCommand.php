<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\ContactSyncService;
use Illuminate\Console\Command;

/**
 * Backfills canonical Contacts for production leads that were created before the
 * inbound-conversation sync gap was closed (leads with contact_id = NULL).
 *
 * Idempotent and re-runnable: ContactSyncService dedups by tenant_id + phone and
 * early-returns when a lead already carries a contact_id. Sandbox/test leads are
 * excluded so the canonical table reflects real CRM identities only.
 */
class BackfillContactsCommand extends Command
{
    protected $signature = 'contacts:backfill {--dry-run : Report how many leads would be linked without writing}';

    protected $description = 'Link production leads missing a contact_id to a canonical Contact';

    public function handle(ContactSyncService $contactSync): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Lead::query()
            ->withoutGlobalScope('tenant')
            ->whereNull('contact_id')
            ->where('is_sandbox', false)
            ->whereNotNull('whatsapp');

        $total = $query->clone()->count();

        if ($total === 0) {
            $this->info('No leads need backfilling — every production lead already has a contact_id.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '').sprintf('%d lead(s) without a contact_id.', $total));

        if ($dryRun) {
            return self::SUCCESS;
        }

        $linked = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(500, function ($leads) use ($contactSync, &$linked, &$skipped, $bar): void {
            foreach ($leads as $lead) {
                $contact = $contactSync->syncFromLead($lead);

                if ($contact !== null) {
                    $linked++;
                } else {
                    $skipped++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf('Done. Linked: %d, skipped (unresolvable phone): %d.', $linked, $skipped));

        return self::SUCCESS;
    }
}
