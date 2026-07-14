<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Services\AlertService;
use App\Services\CampaignService;
use Carbon\Carbon;
use Illuminate\Console\Command;
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
     * Rule 4: Stuck campaign — sending but no messages created in the last hour.
     */
    private function checkStuckCampaigns(AlertService $alertService): void
    {
        $sendingCampaigns = Campaign::where('status', 'sending')->get();

        if ($sendingCampaigns->isEmpty()) {
            return;
        }

        // One grouped query for the latest message per campaign instead of a per-campaign
        // max(created_at) in the loop (PERF-11 — N+1 over all sending campaigns each tick).
        $lastMessageByCampaign = CampaignMessage::whereIn('campaign_id', $sendingCampaigns->modelKeys())
            ->groupBy('campaign_id')
            ->selectRaw('campaign_id, max(created_at) as last_created_at')
            ->pluck('last_created_at', 'campaign_id');

        foreach ($sendingCampaigns as $campaign) {
            $lastMessageAt = $lastMessageByCampaign[$campaign->id] ?? null;

            if ($lastMessageAt && Carbon::parse($lastMessageAt)->lt(now()->subHour())) {
                $alertService->sendAlert(
                    'stuck_campaign',
                    "Campanha '{$campaign->name}' parece travada — nenhuma mensagem enviada há mais de 1 hora.",
                    ['campaign_id' => $campaign->id, 'last_message_at' => $lastMessageAt]
                );
            }
        }
    }

    /**
     * Rule 5: Daily summary at 20:00.
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
