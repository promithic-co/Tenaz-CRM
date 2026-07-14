<?php

namespace App\Console\Commands;

use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Services\AlertService;
use App\Services\CampaignService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitorCampaignsCommand extends Command
{
    protected $signature = 'credflow:monitor-campaigns';

    protected $description = 'Monitor active campaigns for wallet errors, high failure rates, and stuck states';

    public function handle(CampaignService $campaignService, AlertService $alertService): int
    {
        $this->checkWalletErrors($campaignService, $alertService);
        $this->checkHighFailureRate($campaignService, $alertService);
        $this->completeFinishedCampaigns($campaignService);
        $this->reviveIdleCampaigns($campaignService);
        $this->checkStuckCampaigns($alertService);
        $this->maybeSendDailySummary($alertService);

        return self::SUCCESS;
    }

    /**
     * Rule 1: Wallet error (error_code=1003) — auto-pause campaign and alert.
     */
    private function checkWalletErrors(CampaignService $campaignService, AlertService $alertService): void
    {
        // Scan by failed_at, not created_at: a message row is created at dispatch but its
        // 1003 error lands later (via send or delivery webhook). On a slow/large campaign
        // that gap can exceed the window, so filtering on creation time would miss late
        // wallet failures. The window only bounds the query — already-paused campaigns are
        // skipped below via isSending(), so a generous window is safe.
        $walletMessages = CampaignMessage::with('campaign')
            ->where('error_code', '1003')
            ->where('failed_at', '>=', now()->subMinutes(15))
            ->get();

        $pausedCampaignIds = [];

        foreach ($walletMessages as $message) {
            $campaign = $message->campaign;

            if (! $campaign || in_array($campaign->id, $pausedCampaignIds)) {
                continue;
            }

            if ($campaign->isSending()) {
                try {
                    $campaignService->pause($campaign);
                    $pausedCampaignIds[] = $campaign->id;

                    Log::warning('MonitorCampaigns: auto-paused campaign due to wallet error', [
                        'campaign_id' => $campaign->id,
                        'campaign_name' => $campaign->name,
                    ]);

                    $alertService->sendAlert(
                        'wallet_error',
                        "Campanha '{$campaign->name}' pausada automaticamente por erro de carteira (1003).",
                        ['campaign_id' => $campaign->id]
                    );
                } catch (\Throwable $e) {
                    Log::error('MonitorCampaigns: failed to auto-pause campaign', [
                        'campaign_id' => $campaign->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Rule 2: High failure rate — backstop auto-pause at the campaign's own threshold,
     * plus a louder alert past threshold + 5%.
     */
    private function checkHighFailureRate(CampaignService $campaignService, AlertService $alertService): void
    {
        // withCounters() hydrates the message-derived total_* in one query of correlated
        // subqueries (SCALE-1b) — without it, each campaign's accessor would fire its own
        // aggregate, an N+1 over all sending campaigns every tick.
        $sendingCampaigns = Campaign::where('status', 'sending')->withCounters()->get();

        foreach ($sendingCampaigns as $campaign) {
            if ($campaign->total_sent <= 0) {
                continue;
            }

            $failureRate = $campaign->failureRate();

            // Backstop auto-pause (SCALE-1): the hot send path debounces its auto-pause checks,
            // so a failure that loses the debounce race may not trigger an immediate pause. This
            // timer-based sweep guarantees any sending campaign over its own threshold is paused
            // within one monitor cycle. checkAndAutoPause re-evaluates under a row lock.
            if ($failureRate > $campaign->error_threshold_percent && $campaignService->checkAndAutoPause($campaign)) {
                Log::warning('MonitorCampaigns: backstop auto-paused campaign over failure threshold', [
                    'campaign_id' => $campaign->id,
                    'failure_rate' => $failureRate,
                ]);

                continue;
            }

            if ($failureRate > $campaign->error_threshold_percent + 5) {
                $alertService->sendAlert(
                    'high_failure_rate',
                    "Campanha '{$campaign->name}' com taxa de falha alta: {$failureRate}% (limiar: {$campaign->error_threshold_percent}%).",
                    ['campaign_id' => $campaign->id, 'failure_rate' => $failureRate]
                );
            }
        }
    }

    /**
     * Rule 3 (CAMP-03): terminal-state sweep — the only in-band completion check runs inside
     * DispatchCampaignJob at fan-out time, so a campaign whose LAST message settles via a send
     * worker or a delivery webhook stayed `sending` forever. This sweep closes any sending
     * campaign whose work is exhausted, within one monitor cycle.
     *
     * Runs AFTER the pause rules: a campaign over its failure threshold must park as `paused`
     * for operator review, not silently complete. Campaigns with zero message rows are skipped —
     * their fan-out has not run yet (e.g. a smart list awaiting materialization), and completing
     * them here would race DispatchCampaignJob and cancel the whole send; the dispatcher owns
     * the empty-list completion path.
     */
    private function completeFinishedCampaigns(CampaignService $campaignService): void
    {
        $sendingCampaigns = Campaign::where('status', 'sending')->get();

        if ($sendingCampaigns->isEmpty()) {
            return;
        }

        // One grouped query for all sending campaigns (PERF-11): total rows + still-actionable
        // rows per campaign. Only exhausted candidates reach the per-campaign locked re-check.
        $messageStats = CampaignMessage::whereIn('campaign_id', $sendingCampaigns->modelKeys())
            ->groupBy('campaign_id')
            ->selectRaw("campaign_id, count(*) as total_rows, count(case when status in ('pending', 'queued') then 1 end) as actionable_rows")
            ->get()
            ->keyBy('campaign_id');

        foreach ($sendingCampaigns as $campaign) {
            $stats = $messageStats[$campaign->id] ?? null;

            if ($stats === null || (int) $stats->actionable_rows > 0) {
                continue;
            }

            try {
                if ($campaignService->checkAndComplete($campaign)) {
                    Log::info('MonitorCampaigns: completed exhausted campaign', [
                        'campaign_id' => $campaign->id,
                        'campaign_name' => $campaign->name,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('MonitorCampaigns: failed to complete campaign', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Rule 4 (CAMP-04): revive idle work. A budget-stopped fan-out leaves undispatched entries
     * (or parked 'pending' rows) with no scheduled job to ever pick them up — the daily limit
     * used to be a one-way stop. A campaign is idle when it has rows but none queued (nothing
     * in flight); re-dispatch is idempotent (pending-only re-enqueue + atomic provider claim)
     * and gated by remaining daily budget plus a per-campaign debounce so repeated sweeps do
     * not stack dispatchers. Campaigns with zero rows are skipped for the same dispatcher-race
     * reason as the completion sweep above.
     */
    private function reviveIdleCampaigns(CampaignService $campaignService): void
    {
        $sendingCampaigns = Campaign::where('status', 'sending')->get();

        if ($sendingCampaigns->isEmpty()) {
            return;
        }

        $messageStats = CampaignMessage::whereIn('campaign_id', $sendingCampaigns->modelKeys())
            ->groupBy('campaign_id')
            ->selectRaw("campaign_id, count(case when status = 'queued' then 1 end) as queued_rows, count(case when status = 'pending' and provider_attempted_at is null then 1 end) as parked_rows")
            ->get()
            ->keyBy('campaign_id');

        foreach ($sendingCampaigns as $campaign) {
            $stats = $messageStats[$campaign->id] ?? null;

            if ($stats === null || (int) $stats->queued_rows > 0) {
                continue;
            }

            if ((int) $stats->parked_rows === 0 && ! $this->hasUndispatchedEntries($campaign)) {
                continue;
            }

            if ($campaignService->remainingDailyBudget($campaign) <= 0) {
                continue;
            }

            $debounceSeconds = (int) config('credflow.campaigns.revive_debounce_seconds', 600);

            if ($debounceSeconds > 0 && ! Cache::add("campaign:revive-gate:{$campaign->id}", 1, $debounceSeconds)) {
                continue;
            }

            DispatchCampaignJob::dispatch($campaign);

            Log::info('MonitorCampaigns: revived campaign with idle work', [
                'campaign_id' => $campaign->id,
                'parked_rows' => (int) $stats->parked_rows,
            ]);
        }
    }

    private function hasUndispatchedEntries(Campaign $campaign): bool
    {
        return (bool) $campaign->contactList
            ?->entries()
            ->where('opt_in_status', '!=', 'opted_out')
            ->whereNotIn('id', CampaignMessage::where('campaign_id', $campaign->id)->select('contact_list_entry_id'))
            ->exists();
    }

    /**
     * Rule 5: Stuck campaign — has work in flight (queued rows) but nothing has progressed for
     * over an hour.
     *
     * The old check used max(created_at) alone, which is set once at fan-out for the whole batch:
     * a slow-staggered campaign that is sending normally would trip it (rows all created up front),
     * while a genuinely stalled send that already fanned out would never re-trip. It also fired every
     * tick against campaigns legitimately waiting on the next day's budget (CAMP-04) — an expected
     * idle state owned by the revive rule, not a stall.
     *
     * Activity is now the latest of created_at and sent_at, and the alert only considers campaigns
     * with at least one queued row (work that should be moving). Budget-parked campaigns have no
     * queued rows and are silent here. A per-campaign hour-long cache gate caps the alert to once
     * per hour so a persistent stall does not spam every tick.
     */
    private function checkStuckCampaigns(AlertService $alertService): void
    {
        $sendingCampaigns = Campaign::where('status', 'sending')->get();

        if ($sendingCampaigns->isEmpty()) {
            return;
        }

        // One grouped query for last activity + in-flight count per campaign instead of a
        // per-campaign aggregate in the loop (PERF-11 — N+1 over all sending campaigns each tick).
        $statsByCampaign = CampaignMessage::whereIn('campaign_id', $sendingCampaigns->modelKeys())
            ->groupBy('campaign_id')
            ->selectRaw("campaign_id, max(created_at) as last_created_at, max(sent_at) as last_sent_at, count(case when status = 'queued' then 1 end) as queued_rows")
            ->get()
            ->keyBy('campaign_id');

        foreach ($sendingCampaigns as $campaign) {
            $stats = $statsByCampaign[$campaign->id] ?? null;

            if ($stats === null || (int) $stats->queued_rows === 0) {
                continue;
            }

            // Both columns are stored 'Y-m-d H:i:s', so a lexicographic max is also the chronological
            // one; array_filter drops a null last_sent_at (nothing sent yet — created_at carries).
            $activity = array_filter([$stats->last_created_at, $stats->last_sent_at]);

            if ($activity === [] || Carbon::parse(max($activity))->gte(now()->subHour())) {
                continue;
            }

            if (! Cache::add("campaign:stuck-alert:{$campaign->id}", 1, 3600)) {
                continue;
            }

            $alertService->sendAlert(
                'stuck_campaign',
                "Campanha '{$campaign->name}' parece travada — nenhuma mensagem enviada há mais de 1 hora.",
                ['campaign_id' => $campaign->id, 'last_activity_at' => max($activity)]
            );
        }
    }

    /**
     * Rule 6: Daily summary at 20:00.
     */
    private function maybeSendDailySummary(AlertService $alertService): void
    {
        if (Carbon::now()->hour !== 20) {
            return;
        }

        $completed = Campaign::where('status', 'completed')->whereDate('completed_at', today())->count();
        $failed = Campaign::where('status', 'failed')->whereDate('updated_at', today())->count();
        $totalSentToday = CampaignMessage::whereDate('created_at', today())->whereIn('status', ['sent', 'delivered', 'read'])->count();
        $totalDeliveredToday = CampaignMessage::whereDate('delivered_at', today())->count();
        $deliveryRate = $totalSentToday > 0 ? round($totalDeliveredToday / $totalSentToday * 100, 1) : 0;

        $alertService->sendAlert(
            'daily_summary',
            "Resumo diário de campanhas — Concluídas: {$completed}, Falhas: {$failed}, Mensagens enviadas: {$totalSentToday}, Taxa de entrega: {$deliveryRate}%.",
            [
                'completed_today' => $completed,
                'failed_today' => $failed,
                'sent_today' => $totalSentToday,
                'delivered_today' => $totalDeliveredToday,
                'delivery_rate' => $deliveryRate,
            ]
        );
    }
}
