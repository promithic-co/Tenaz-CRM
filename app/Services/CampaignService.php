<?php

namespace App\Services;

use App\Events\CampaignStatusChanged;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    /**
     * Start a campaign. Guard: must be draft or scheduled, template must be APPROVED.
     */
    public function start(Campaign $campaign): void
    {
        $totalRecipients = $campaign->contactList?->entries()->count() ?? 0;

        // Concurrency guard (DB-5): lock the row, re-evaluate the guard against the locked
        // state, then transition — so a control action racing the dispatcher or another
        // control action cannot both pass canStart() and clobber each other (last-writer-wins).
        DB::transaction(function () use ($campaign, $totalRecipients): void {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->canStart()) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser iniciada (status: {$campaign->status}).");
            }

            if (! $locked->whatsappTemplate?->isApproved()) {
                throw new \RuntimeException('O template da campanha não está aprovado.');
            }

            $locked->update([
                'status' => 'sending',
                'started_at' => now(),
                'paused_at' => null,
                'total_recipients' => $totalRecipients,
            ]);

            $campaign->setRawAttributes($locked->getAttributes());
        });

        Log::info('CampaignService.start', ['campaign_id' => $campaign->id, 'recipients' => $totalRecipients]);

        DispatchCampaignJob::dispatch($campaign);
    }

    /**
     * Pause a running campaign.
     */
    public function pause(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->canPause()) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser pausada (status: {$campaign->status}).");
            }

            $locked->update([
                'status' => 'paused',
                'paused_at' => now(),
            ]);

            $campaign->setRawAttributes($locked->getAttributes());
        });

        Log::info('CampaignService.pause', ['campaign_id' => $campaign->id]);
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->canResume()) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser retomada (status: {$campaign->status}).");
            }

            $locked->update([
                'status' => 'sending',
                'paused_at' => null,
            ]);

            $campaign->setRawAttributes($locked->getAttributes());
        });

        Log::info('CampaignService.resume', ['campaign_id' => $campaign->id]);

        DispatchCampaignJob::dispatch($campaign);
    }

    /**
     * Cancel a sending or paused campaign.
     */
    public function cancel(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, ['sending', 'paused'], true)) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser cancelada (status: {$campaign->status}).");
            }

            $locked->update([
                'status' => 'cancelled',
                'failure_reason' => 'Cancelada manualmente',
            ]);

            $campaign->setRawAttributes($locked->getAttributes());
        });

        Log::info('CampaignService.cancel', ['campaign_id' => $campaign->id]);
    }

    /**
     * Check if the failure rate exceeds the threshold, and auto-pause if so.
     * Wallet errors (error_code 1003) are owned by MonitorCampaignsCommand,
     * which scans for them on a schedule — kept out of this hot per-failure
     * path to avoid an extra query on every failed message.
     * Returns true if the campaign was paused.
     */
    public function checkAndAutoPause(Campaign $campaign): bool
    {
        // Scale guard (SCALE-1): collapse the per-failure auto-pause convoy. This check is
        // invoked from every failure branch of SendCampaignMessageJob, so a failure storm had
        // up to one concurrent send worker per process all taking an exclusive lockForUpdate on
        // the single campaign row. A cheap atomic debounce gate lets only the first caller per
        // short window reach the locked evaluation below; the rest skip it. MonitorCampaignsCommand
        // is the timer-based backstop for any failure that loses the gate race. Fail open on a
        // cache outage so a degraded cache can never silently disable the safety control.
        $debounceSeconds = (int) config('credflow.campaigns.autopause_debounce_seconds', 3);

        if ($debounceSeconds > 0) {
            try {
                $wonGate = Cache::add("campaign:autopause-gate:{$campaign->getKey()}", 1, $debounceSeconds);
            } catch (\Throwable $e) {
                $wonGate = true;
            }

            if (! $wonGate) {
                return false;
            }
        }

        // Concurrency guard (DB-5): evaluate + pause against a locked row so the auto-pause
        // safety control cannot be clobbered by a racing resume (which would let a campaign
        // keep sending past its failure threshold and keep burning Meta reputation).
        return DB::transaction(function () use ($campaign): bool {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->isSending()) {
                return false;
            }

            // Check if we have enough sent messages to evaluate threshold
            if ($locked->total_sent < 10) {
                return false;
            }

            if ($locked->failureRate() > $locked->error_threshold_percent) {
                $locked->update([
                    'status' => 'paused',
                    'paused_at' => now(),
                    'failure_reason' => "Taxa de falha ({$locked->failureRate()}%) excedeu o limite ({$locked->error_threshold_percent}%).",
                ]);

                Log::warning('CampaignService.auto_pause_threshold', [
                    'campaign_id' => $locked->id,
                    'failure_rate' => $locked->failureRate(),
                ]);

                $campaign->setRawAttributes($locked->getAttributes());

                return true;
            }

            return false;
        });
    }

    /**
     * Complete a campaign whose work is exhausted (CAMP-03). Complete = no message row is
     * still actionable (pending/queued — in flight or re-enqueueable) AND every dispatchable
     * entry already has a row (fan-out finished; opted-out entries never get a row by design).
     * The old counter predicate (total_sent >= total_recipients) could never close a campaign
     * with a single failure or opt-out skip. in_doubt rows do not block: they are terminal by
     * design (never re-sent) and a late webhook still upgrades their counters after completion.
     * Same locked-row shape as the other transitions (DB-5) so a racing pause/cancel wins.
     * Returns true if the campaign was completed.
     */
    public function checkAndComplete(Campaign $campaign): bool
    {
        return DB::transaction(function () use ($campaign): bool {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->isSending()) {
                return false;
            }

            $hasActionableMessages = CampaignMessage::where('campaign_id', $locked->id)
                ->whereIn('status', ['pending', 'queued'])
                ->exists();

            if ($hasActionableMessages) {
                return false;
            }

            $hasUndispatchedEntries = (bool) $locked->contactList
                ?->entries()
                ->where('opt_in_status', '!=', 'opted_out')
                ->whereNotIn('id', CampaignMessage::where('campaign_id', $locked->id)->select('contact_list_entry_id'))
                ->exists();

            if ($hasUndispatchedEntries) {
                return false;
            }

            $locked->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $campaign->setRawAttributes($locked->getAttributes());

            CampaignStatusChanged::dispatch($locked->id, 'completed');

            Log::info('CampaignService.complete', ['campaign_id' => $locked->id]);

            return true;
        });
    }

    /**
     * Check if the campaign is under its daily send limit.
     * Returns true if under the limit (can continue sending).
     */
    public function checkDailyLimit(Campaign $campaign): bool
    {
        // Sargable range (SCALE-6): whereDate() wraps the column in a function and cannot use
        // an index — a full scan on every 500-entry chunk of the dispatcher. The explicit
        // [start-of-day, end-of-day] range is index-friendly against (campaign_id, sent_at)
        // and implicitly excludes null sent_at.
        $sentToday = CampaignMessage::where('campaign_id', $campaign->id)
            ->whereBetween('sent_at', [today()->startOfDay(), today()->endOfDay()])
            ->count();

        return $sentToday < $campaign->daily_limit;
    }

    /**
     * Remaining sends allowed today (CAMP-04): daily_limit minus messages already sent today
     * minus rows currently queued (live jobs that consume budget when they pop). Parked
     * 'pending' rows do not count — they only send again once a revive re-enqueues them
     * inside a future day's budget. The "day" is the application timezone.
     */
    public function remainingDailyBudget(Campaign $campaign): int
    {
        $sentToday = CampaignMessage::where('campaign_id', $campaign->id)
            ->whereBetween('sent_at', [today()->startOfDay(), today()->endOfDay()])
            ->count();

        $inFlight = CampaignMessage::where('campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->count();

        return max(0, $campaign->daily_limit - $sentToday - $inFlight);
    }
}
