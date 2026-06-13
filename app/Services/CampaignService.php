<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    /**
     * Start a campaign. Guard: must be draft or scheduled, template must be APPROVED.
     */
    public function start(Campaign $campaign): void
    {
        if (! $campaign->canStart()) {
            throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser iniciada (status: {$campaign->status}).");
        }

        if (! $campaign->whatsappTemplate?->isApproved()) {
            throw new \RuntimeException('O template da campanha não está aprovado.');
        }

        $totalRecipients = $campaign->contactList->entries()->count();

        $campaign->update([
            'status' => 'sending',
            'started_at' => now(),
            'paused_at' => null,
            'total_recipients' => $totalRecipients,
        ]);

        Log::info('CampaignService.start', ['campaign_id' => $campaign->id, 'recipients' => $totalRecipients]);

        \App\Jobs\DispatchCampaignJob::dispatch($campaign);
    }

    /**
     * Pause a running campaign.
     */
    public function pause(Campaign $campaign): void
    {
        if (! $campaign->canPause()) {
            throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser pausada (status: {$campaign->status}).");
        }

        $campaign->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        Log::info('CampaignService.pause', ['campaign_id' => $campaign->id]);
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(Campaign $campaign): void
    {
        if (! $campaign->canResume()) {
            throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser retomada (status: {$campaign->status}).");
        }

        $campaign->update([
            'status' => 'sending',
            'paused_at' => null,
        ]);

        Log::info('CampaignService.resume', ['campaign_id' => $campaign->id]);

        \App\Jobs\DispatchCampaignJob::dispatch($campaign);
    }

    /**
     * Cancel a sending or paused campaign.
     */
    public function cancel(Campaign $campaign): void
    {
        if (! in_array($campaign->status, ['sending', 'paused'])) {
            throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser cancelada (status: {$campaign->status}).");
        }

        $campaign->update([
            'status' => 'failed',
            'failure_reason' => 'Cancelada manualmente',
        ]);

        Log::info('CampaignService.cancel', ['campaign_id' => $campaign->id]);
    }

    /**
     * Check if the failure rate exceeds the threshold, and auto-pause if so.
     * Also immediately pauses on error_code 1003 (wallet/insufficient funds).
     * Returns true if the campaign was paused.
     */
    public function checkAndAutoPause(Campaign $campaign): bool
    {
        $campaign->refresh();

        if (! $campaign->isSending()) {
            return false;
        }

        // Immediate pause on wallet error (1003) - check last failed message
        $lastFailed = CampaignMessage::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->latest('failed_at')
            ->first();

        if ($lastFailed && $lastFailed->error_code === '1003') {
            $campaign->update([
                'status' => 'paused',
                'paused_at' => now(),
                'failure_reason' => 'Saldo insuficiente (erro 1003). Campanha pausada automaticamente.',
            ]);

            Log::warning('CampaignService.auto_pause_wallet', ['campaign_id' => $campaign->id]);

            return true;
        }

        // Check if we have enough sent messages to evaluate threshold
        if ($campaign->total_sent < 10) {
            return false;
        }

        if ($campaign->failureRate() > $campaign->error_threshold_percent) {
            $campaign->update([
                'status' => 'paused',
                'paused_at' => now(),
                'failure_reason' => "Taxa de falha ({$campaign->failureRate()}%) excedeu o limite ({$campaign->error_threshold_percent}%).",
            ]);

            Log::warning('CampaignService.auto_pause_threshold', [
                'campaign_id' => $campaign->id,
                'failure_rate' => $campaign->failureRate(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Check if the campaign is under its daily send limit.
     * Returns true if under the limit (can continue sending).
     */
    public function checkDailyLimit(Campaign $campaign): bool
    {
        $sentToday = CampaignMessage::where('campaign_id', $campaign->id)
            ->whereNotNull('sent_at')
            ->whereDate('sent_at', today())
            ->count();

        return $sentToday < $campaign->daily_limit;
    }
}
