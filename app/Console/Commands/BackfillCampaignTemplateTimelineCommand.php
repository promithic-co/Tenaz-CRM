<?php

namespace App\Console\Commands;

use App\Models\CampaignMessage;
use App\Models\Lead;
use App\Services\CampaignConversationTimelineWriter;
use Illuminate\Console\Command;

/**
 * Replays historical campaign templates into the conversation timeline.
 *
 * Campaign sends only started mirroring into the timeline partway through the product's life,
 * and even after that the mirror matched phones as exact strings — so a lead stored with the
 * BR 9th digit never matched a campaign entry stored without it. Both gaps leave the same
 * symptom: a conversation whose first visible message is the customer's reply, with no trace
 * of the template that provoked it.
 *
 * Resolves the lead per campaign message using every phone variant, then delegates to the
 * canonical writer so the row shape stays identical to a live mirror. Idempotent on
 * provider_message_id — safe to re-run, and safe to run while campaigns are sending.
 */
class BackfillCampaignTemplateTimelineCommand extends Command
{
    protected $signature = 'timeline:backfill-campaign-templates
        {--tenant= : Restrict to a single tenant id}
        {--days=90 : Only replay campaign messages sent within this many days}
        {--limit=25 : Maximum campaign messages replayed per lead}
        {--chunk=500 : Leads processed per batch}
        {--dry-run : Report what would be written without touching the timeline}';

    protected $description = 'Replay historical campaign templates into the conversation timeline';

    public function handle(CampaignConversationTimelineWriter $writer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = max(1, (int) $this->option('days'));
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = max(1, (int) $this->option('limit'));
        $tenant = $this->option('tenant');

        $leads = Lead::query()
            ->withoutGlobalScopes()
            ->when($tenant !== null, fn ($query) => $query->where('tenant_id', (string) $tenant));

        $total = $leads->clone()->count();

        if ($total === 0) {
            $this->info('No leads to inspect.');

            return self::SUCCESS;
        }

        // The writer decides per message whether a row is missing, so a dry run reports the
        // upper bound: campaign messages in range that could still be replayed.
        if ($dryRun) {
            $candidates = CampaignMessage::query()
                ->whereNotNull('provider_message_id')
                ->whereIn('status', ['sent', 'delivered', 'read'])
                ->where('sent_at', '>=', now()->subDays($days))
                ->when($tenant !== null, fn ($query) => $query->whereHas(
                    'campaign',
                    fn ($q) => $q->where('tenant_id', (string) $tenant),
                ))
                ->count();

            $this->info(sprintf(
                '[DRY RUN] %d lead(s) would be inspected against %d campaign message(s) sent in the last %d day(s).',
                $total,
                $candidates,
                $days,
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf('Inspecting %d lead(s) against campaign sends from the last %d day(s).', $total, $days));

        $processed = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $leads->chunkById($chunk, function ($batch) use ($writer, $days, $limit, &$processed, $bar): void {
            foreach ($batch as $lead) {
                $writer->backfillForLead($lead, lookbackDays: $days, limit: $limit);
                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf('Done. Leads inspected: %d.', $processed));

        return self::SUCCESS;
    }
}
