<?php

namespace App\Services;

use App\Jobs\DispatchVoiceCampaignJob;
use App\Models\VoiceCampaign;
use Illuminate\Support\Facades\Log;

class VoiceCampaignService
{
    /**
     * Start a voice campaign. Guard: must be in draft status with DTMF configured.
     */
    public function start(VoiceCampaign $campaign): void
    {
        if (! $campaign->canStart()) {
            throw new \RuntimeException("Campanha de voz '{$campaign->name}' não pode ser iniciada (status: {$campaign->status}).");
        }

        if (! $campaign->hasDtmfConfigured()) {
            throw new \RuntimeException("Configure pelo menos uma ação DTMF antes de iniciar a campanha '{$campaign->name}'.");
        }

        $totalCalls = $campaign->contactList->entries()->optedIn()->count();

        $campaign->update([
            'status' => 'sending',
            'started_at' => now(),
            'paused_at' => null,
            'total_calls' => $totalCalls,
        ]);

        Log::info('VoiceCampaignService.start', ['campaign_id' => $campaign->id, 'total_calls' => $totalCalls]);

        DispatchVoiceCampaignJob::dispatch($campaign);
    }

    /**
     * Pause a running voice campaign.
     */
    public function pause(VoiceCampaign $campaign): void
    {
        if (! $campaign->canPause()) {
            throw new \RuntimeException("Campanha de voz '{$campaign->name}' não pode ser pausada (status: {$campaign->status}).");
        }

        $campaign->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        Log::info('VoiceCampaignService.pause', ['campaign_id' => $campaign->id]);
    }

    /**
     * Resume a paused voice campaign. Redispatches DispatchVoiceCampaignJob to resume pending calls.
     */
    public function resume(VoiceCampaign $campaign): void
    {
        if (! $campaign->canResume()) {
            throw new \RuntimeException("Campanha de voz '{$campaign->name}' não pode ser retomada (status: {$campaign->status}).");
        }

        $campaign->update([
            'status' => 'sending',
            'paused_at' => null,
        ]);

        Log::info('VoiceCampaignService.resume', ['campaign_id' => $campaign->id]);

        DispatchVoiceCampaignJob::dispatch($campaign);
    }
}
