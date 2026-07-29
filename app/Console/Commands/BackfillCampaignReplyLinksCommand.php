<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\CampaignReplyDetector;
use Illuminate\Console\Command;

/**
 * Links replies that arrived while the detector was still matching phones exactly.
 *
 * {@see CampaignReplyDetector} looked recipients up by the exact phone string. A CSV import
 * stores a BR mobile with the 9th digit and the inbound webhook without it, so a reply from
 * a campaign recipient resolved to nothing and the lead kept a null campaign_id. The
 * campaign then reported zero replies while the operator could read the answers in the
 * inbox — which is how a run that reached people looked like a run that reached nobody.
 *
 * Fixing the detector only helps replies that arrive from now on; the rows already written
 * stay unlinked until this runs. Idempotent, and it never overwrites an existing link.
 */
class BackfillCampaignReplyLinksCommand extends Command
{
    protected $signature = 'campaigns:backfill-reply-links
        {--tenant= : Restrict to a single tenant id}
        {--chunk=500 : Leads processed per batch}
        {--dry-run : Report the links without writing}';

    protected $description = 'Link past campaign replies whose lead was left without a campaign_id';

    public function handle(CampaignReplyDetector $detector): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $tenant = $this->option('tenant');

        // Replied (last_inbound_at is stamped only by a real inbound message) but never
        // attributed. A lead that never replied is deliberately left alone: since campaign
        // sends create a lead per recipient, linking those would report the whole list as
        // having answered — the exact lie the counters were fixed to stop telling.
        $leads = Lead::query()
            ->withoutGlobalScopes()
            ->whereNotNull('last_inbound_at')
            ->whereNull('campaign_id')
            ->whereNotNull('whatsapp')
            ->when($tenant !== null, fn ($query) => $query->where('tenant_id', (string) $tenant));

        $total = $leads->clone()->count();

        if ($total === 0) {
            $this->info('No unlinked replies to repair.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Inspecting %d replied lead(s) with no campaign.', $total));

        $rows = [];
        $linked = 0;

        $leads->chunkById($chunk, function ($batch) use ($detector, $dryRun, &$rows, &$linked): void {
            foreach ($batch as $lead) {
                $campaign = $detector->resolveCampaign(
                    (string) $lead->whatsapp,
                    (string) $lead->tenant_id,
                    CampaignReplyDetector::HISTORICAL_STATUSES,
                );

                if ($campaign === null) {
                    continue;
                }

                $rows[] = [$lead->id, $lead->whatsapp, $lead->nome, $campaign->id, $campaign->name];

                if ($dryRun) {
                    continue;
                }

                $lead->update(['campaign_id' => $campaign->id]);
                $linked++;
            }
        });

        if ($rows === []) {
            $this->info('No replied lead resolved to a campaign.');

            return self::SUCCESS;
        }

        $this->table(['Lead', 'Phone', 'Name', 'Campaign', 'Campaign name'], $rows);

        if ($dryRun) {
            $this->info(sprintf('[DRY RUN] %d link(s) would be written.', count($rows)));

            return self::SUCCESS;
        }

        $this->info(sprintf('Linked %d repl(ies) to their campaign.', $linked));

        return self::SUCCESS;
    }
}
