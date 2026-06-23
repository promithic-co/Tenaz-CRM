<?php

namespace App\Jobs;

use App\Models\ContactListEntry;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignCall;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

// Multi-tenant safety: all queries are scoped by voice_campaign_id FK (globally unique).
// BelongsToTenant global scope is inactive in queue context, but no cross-tenant
// data can be accessed since VoiceCampaignCall queries filter by specific voice_campaign_id.
class DispatchVoiceCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly VoiceCampaign $voiceCampaign,
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(): void
    {
        $campaign = $this->voiceCampaign->fresh();

        if (! $campaign || ! $campaign->isSending()) {
            Log::info('DispatchVoiceCampaignJob: campaign not in sending state, skipping', [
                'campaign_id' => $this->voiceCampaign->id,
                'status' => $campaign?->status,
            ]);

            return;
        }

        $eligibleTotal = $campaign->contactList->entries()->optedIn()->count();

        if ($campaign->total_calls !== $eligibleTotal) {
            $campaign->update(['total_calls' => $eligibleTotal]);
            $campaign->total_calls = $eligibleTotal;
        }

        $index = 0;
        $stopped = false;

        // Greeting template is immutable for the campaign's life; resolve it once here
        // instead of touching the lazy voiceInstance relation on every entry inside the
        // chunk loop (PERF-7).
        $greetingTemplate = $campaign->greeting_template
            ?? $campaign->voiceInstance?->greeting_template
            ?? '';

        // Stream all opted-in entries and skip already-called ones per chunk via a
        // bounded whereIn (mirrors DispatchCampaignJob). The previous whereNotIn anti-join
        // re-scanned every prior call on each chunk → O(chunks × priorCalls) on a
        // re-dispatched campaign. Bounded lookups hit the (voice_campaign_id,
        // contact_list_entry_id) index instead.
        $campaign->contactList->entries()
            ->optedIn()
            ->select(['id', 'phone', 'name', 'extra_data'])
            ->orderBy('id')
            ->chunkById(100, function ($entries) use ($campaign, $greetingTemplate, &$index, &$stopped) {
                // Re-check the live status each chunk so a mid-dispatch pause/cancel stops the
                // fan-out — but read only the status column instead of $campaign->refresh(),
                // which reloaded the loaded contactList relation on every chunk (SCALE-5).
                $status = VoiceCampaign::withoutGlobalScopes()->whereKey($campaign->id)->value('status');

                if ($status !== 'sending') {
                    Log::info('DispatchVoiceCampaignJob: campaign paused/cancelled mid-dispatch', ['campaign_id' => $campaign->id]);
                    $stopped = true;

                    return false;
                }

                $chunkIds = $entries->pluck('id')->all();

                $existingSet = array_flip(
                    VoiceCampaignCall::where('voice_campaign_id', $campaign->id)
                        ->whereIn('contact_list_entry_id', $chunkIds)
                        ->pluck('contact_list_entry_id')
                        ->all()
                );

                foreach ($entries as $entry) {
                    if (isset($existingSet[$entry->id])) {
                        continue;
                    }

                    $phone = str_starts_with($entry->phone, '+') ? $entry->phone : '+'.$entry->phone;

                    $interpolatedMessage = $this->interpolateTemplate($greetingTemplate, $entry);

                    $voiceCampaignCall = VoiceCampaignCall::firstOrCreate(
                        ['voice_campaign_id' => $campaign->id, 'contact_list_entry_id' => $entry->id],
                        [
                            'phone' => $phone,
                            'contact_name' => $entry->name ?? '',
                            'interpolated_message' => $interpolatedMessage,
                            'status' => 'pending',
                        ]
                    );

                    PlaceOutboundCallJob::dispatch($voiceCampaignCall)
                        ->delay(now()->addMilliseconds($index * $campaign->delay_between_calls_ms));

                    $index++;
                }
            });

        if ($stopped) {
            Log::info('DispatchVoiceCampaignJob: dispatched calls', [
                'campaign_id' => $campaign->id,
                'count' => $index,
            ]);

            return;
        }

        if ($index === 0) {
            Log::info('DispatchVoiceCampaignJob: no pending entries', ['campaign_id' => $campaign->id]);

            if ($campaign->total_calls === 0) {
                $campaign->update(['status' => 'completed', 'completed_at' => now()]);

                return;
            }

            $processed = VoiceCampaignCall::where('voice_campaign_id', $campaign->id)
                ->whereNotIn('status', ['pending', 'calling'])
                ->count();

            if ($processed >= $campaign->total_calls) {
                $campaign->update(['status' => 'completed', 'completed_at' => now()]);
            }

            return;
        }

        Log::info('DispatchVoiceCampaignJob: dispatched calls', [
            'campaign_id' => $campaign->id,
            'count' => $index,
        ]);
    }

    /**
     * Terminal signal (REL-4 / SCALE-11): tries=1 means a mid-fan-out crash strands the
     * voice campaign in `sending` with no retry. Log an actionable breadcrumb, then
     * auto-resume the remainder — re-dispatch is idempotent (per-chunk whereIn skip +
     * firstOrCreate), bounded by a per-campaign budget so a deterministic crash cannot loop.
     */
    public function failed(Throwable $e): void
    {
        Log::error('DispatchVoiceCampaignJob.failed', [
            'campaign_id' => $this->voiceCampaign->id,
            'error' => $e->getMessage(),
        ]);

        $this->attemptResume();
    }

    /**
     * Re-dispatch the idempotent remainder after a crash, bounded by a per-campaign budget.
     */
    private function attemptResume(): void
    {
        $max = (int) config('credflow.campaigns.dispatch_max_redispatch', 0);

        if ($max <= 0) {
            return;
        }

        $campaign = $this->voiceCampaign->fresh();

        if (! $campaign || ! $campaign->isSending()) {
            return;
        }

        $key = "voice_campaign_dispatch_resume:{$campaign->id}";
        $used = (int) Cache::get($key, 0);

        if ($used >= $max) {
            Log::error('DispatchVoiceCampaignJob.failed: auto-resume budget exhausted, manual resume required', [
                'campaign_id' => $campaign->id,
                'attempts' => $used,
            ]);

            return;
        }

        Cache::put($key, $used + 1, now()->addHours(6));

        $delay = (int) config('credflow.campaigns.dispatch_redispatch_delay_seconds', 10);
        self::dispatch($campaign)->delay(now()->addSeconds($delay));

        Log::warning('DispatchVoiceCampaignJob.failed: auto-resuming remainder', [
            'campaign_id' => $campaign->id,
            'attempt' => $used + 1,
            'max' => $max,
        ]);
    }

    private function interpolateTemplate(string $template, ContactListEntry $entry): string
    {
        $vars = array_merge(['nome' => $entry->name ?? ''], $entry->extra_data ?? []);

        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => $vars[$m[1]] ?? $m[0], $template);
    }
}
