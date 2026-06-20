<?php

namespace App\Jobs;

use App\Models\ContactListEntry;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignCall;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

        $pendingEntries = $campaign->contactList->entries()
            ->optedIn()
            ->whereNotIn('id', VoiceCampaignCall::query()
                ->select('contact_list_entry_id')
                ->where('voice_campaign_id', $campaign->id)
                ->whereNotNull('contact_list_entry_id')
            )
            ->select(['id', 'phone', 'name', 'extra_data']);

        if (! $pendingEntries->exists()) {
            Log::info('DispatchVoiceCampaignJob: no pending entries', ['campaign_id' => $campaign->id]);

            if ($campaign->total_calls === 0) {
                $campaign->update(['status' => 'completed', 'completed_at' => now()]);

                return;
            }

            if ($campaign->total_calls > 0) {
                $processed = VoiceCampaignCall::where('voice_campaign_id', $campaign->id)
                    ->whereNotIn('status', ['pending', 'calling'])
                    ->count();

                if ($processed >= $campaign->total_calls) {
                    $campaign->update(['status' => 'completed', 'completed_at' => now()]);
                }
            }

            return;
        }

        $index = 0;

        $pendingEntries->chunkById(100, function ($entries) use ($campaign, &$index) {
            $campaign->refresh();

            if (! $campaign->isSending()) {
                Log::info('DispatchVoiceCampaignJob: campaign paused/cancelled mid-dispatch', ['campaign_id' => $campaign->id]);

                return false;
            }

            foreach ($entries as $entry) {
                $phone = str_starts_with($entry->phone, '+') ? $entry->phone : '+'.$entry->phone;

                $template = $campaign->greeting_template
                    ?? $campaign->voiceInstance->greeting_template
                    ?? '';

                $interpolatedMessage = $this->interpolateTemplate($template, $entry);

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

        Log::info('DispatchVoiceCampaignJob: dispatched calls', [
            'campaign_id' => $campaign->id,
            'count' => $index,
        ]);
    }

    /**
     * Terminal signal (REL-4): tries=1 means a mid-fan-out crash strands the voice
     * campaign in `sending` with no retry. Log an actionable breadcrumb; re-dispatch is
     * idempotent (whereNotIn + firstOrCreate) and resumes only the un-enqueued remainder.
     */
    public function failed(Throwable $e): void
    {
        Log::error('DispatchVoiceCampaignJob.failed', [
            'campaign_id' => $this->voiceCampaign->id,
            'error' => $e->getMessage(),
        ]);
    }

    private function interpolateTemplate(string $template, ContactListEntry $entry): string
    {
        $vars = array_merge(['nome' => $entry->name ?? ''], $entry->extra_data ?? []);

        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => $vars[$m[1]] ?? $m[0], $template);
    }
}
