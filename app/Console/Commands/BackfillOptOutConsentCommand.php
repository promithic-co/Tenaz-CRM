<?php

namespace App\Console\Commands;

use App\Listeners\PropagateOptOutOnLeadStatusChanged;
use App\Models\Lead;
use App\Services\OptOutConsentPropagator;
use Illuminate\Console\Command;

/**
 * Repairs consent for leads that opted out before the propagation existed.
 *
 * Until {@see PropagateOptOutOnLeadStatusChanged} landed, answering a
 * campaign template with "bloquear" only moved the lead to `optou_sair` — the columns the
 * send gate reads were left untouched, so every one of those recipients was still eligible
 * for the next send. This walks the existing `optou_sair` leads and writes the record that
 * should have been written at the time.
 *
 * Idempotent (already-suppressed rows are skipped), so it is safe to re-run and safe to run
 * while campaigns are sending. Run it before resuming any paused campaign.
 */
class BackfillOptOutConsentCommand extends Command
{
    protected $signature = 'consent:backfill-opt-outs
        {--tenant= : Restrict to a single tenant id}
        {--chunk=500 : Leads processed per batch}
        {--dry-run : Report what would be suppressed without writing}';

    protected $description = 'Write consent records for leads that reached optou_sair before propagation existed';

    public function handle(OptOutConsentPropagator $propagator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $tenant = $this->option('tenant');

        $leads = Lead::query()
            ->withoutGlobalScopes()
            ->where('status', 'optou_sair')
            ->when($tenant !== null, fn ($query) => $query->where('tenant_id', (string) $tenant));

        $total = $leads->clone()->count();

        if ($total === 0) {
            $this->info('No opted-out leads to repair.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info(sprintf('[DRY RUN] %d opted-out lead(s) would be inspected.', $total));

            return self::SUCCESS;
        }

        $this->info(sprintf('Inspecting %d opted-out lead(s).', $total));

        $contacts = 0;
        $entries = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $leads->chunkById($chunk, function ($batch) use ($propagator, &$contacts, &$entries, $bar): void {
            foreach ($batch as $lead) {
                $changed = $propagator->propagate($lead);
                $contacts += $changed['contacts'];
                $entries += $changed['entries'];
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf('Done. Contacts suppressed: %d. List entries suppressed: %d.', $contacts, $entries));

        return self::SUCCESS;
    }
}
