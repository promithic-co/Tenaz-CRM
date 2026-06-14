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
        $this->checkHighFailureRate($alertService);
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
     * Rule 2: High failure rate (> threshold + 5%) — alert only.
     */
    private function checkHighFailureRate(AlertService $alertService): void
    {
        $sendingCampaigns = Campaign::where('status', 'sending')
            ->whereColumn('total_sent', '>', 'total_recipients * 0')
            ->get();

        foreach ($sendingCampaigns as $campaign) {
            if ($campaign->total_sent <= 0) {
                continue;
            }

            $failureRate = $campaign->failureRate();
            $threshold = $campaign->error_threshold_percent + 5;

            if ($failureRate > $threshold) {
                $alertService->sendAlert(
                    'high_failure_rate',
                    "Campanha '{$campaign->name}' com taxa de falha alta: {$failureRate}% (limiar: {$campaign->error_threshold_percent}%).",
                    ['campaign_id' => $campaign->id, 'failure_rate' => $failureRate]
                );
            }
        }
    }

    /**
     * Rule 3: Stuck campaign — sending but no messages created in the last hour.
     */
    private function checkStuckCampaigns(AlertService $alertService): void
    {
        $sendingCampaigns = Campaign::where('status', 'sending')->get();

        foreach ($sendingCampaigns as $campaign) {
            $lastMessageAt = CampaignMessage::where('campaign_id', $campaign->id)
                ->max('created_at');

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
     * Rule 4: Daily summary at 20:00.
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
