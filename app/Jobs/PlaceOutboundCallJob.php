<?php

namespace App\Jobs;

use App\Models\VoiceCampaignCall;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use Twilio\Rest\Client;

class PlaceOutboundCallJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly VoiceCampaignCall $voiceCampaignCall,
    ) {
        $this->onQueue('campaigns');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(): void
    {
        $call = $this->voiceCampaignCall->load('voiceCampaign');
        $voiceCampaign = $call->voiceCampaign;

        if (! $voiceCampaign->isSending()) {
            Log::info('PlaceOutboundCallJob: campaign not sending, aborting', [
                'call_id' => $call->id,
                'campaign_status' => $voiceCampaign->status,
            ]);

            return;
        }

        // Idempotency: never place the same call twice. A persisted call_sid means a prior
        // attempt already succeeded; the cache claim covers the lost-response window where
        // tries=3 would re-dial (and re-bill PSTN minutes) after the Twilio POST reached Twilio.
        if ($call->call_sid !== null) {
            Log::info('ivr.call_already_placed', [
                'call_id' => $call->id,
                'call_sid' => $call->call_sid,
            ]);

            return;
        }

        if (! Cache::add("voice_call_place:{$call->id}", 1, now()->addMinutes(10))) {
            Log::info('ivr.call_place_already_claimed', [
                'call_id' => $call->id,
            ]);

            return;
        }

        $client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        $twilioCall = $client->calls->create(
            $call->phone,
            config('services.twilio.phone_number'),
            [
                'url' => route('ivr.script', $call),
                'statusCallback' => route('ivr.status', $call),
                'statusCallbackEvent' => ['completed', 'busy', 'no-answer', 'failed'],
                'statusCallbackMethod' => 'POST',
                'method' => 'POST',
                'timeout' => 30,
            ]
        );

        $call->update([
            'call_sid' => $twilioCall->sid,
            'status' => 'calling',
            'called_at' => now(),
        ]);

        Log::info('ivr.call_placed', [
            'call_id' => $call->id,
            'call_sid' => $twilioCall->sid,
            'phone' => $call->phone,
            'campaign_id' => $voiceCampaign->id,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $call = $this->voiceCampaignCall;

        // Idempotency guard (REL-3): only finalize a still-in-flight call. If the Twilio
        // status callback already moved this row to a terminal status, incrementing here
        // would double-count total_failed. Count only when this handler performs the
        // pending/calling -> failed transition (affected-rows == 1).
        $affected = VoiceCampaignCall::whereKey($call->id)
            ->whereIn('status', ['pending', 'calling'])
            ->update(['status' => 'failed']);

        if ($affected === 1) {
            $call->voiceCampaign()->increment('total_failed');
        }

        Log::error('PlaceOutboundCallJob.failed', [
            'call_id' => $call->id,
            'phone' => $call->phone,
            'error' => $e->getMessage(),
            'counted' => $affected === 1,
        ]);
    }
}
