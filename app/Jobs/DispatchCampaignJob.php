<?php

namespace App\Jobs;

use App\Events\CampaignStatusChanged;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Services\AgentInteractionEventService;
use App\Services\CampaignService;
use App\Services\SmartList\SmartListResolverService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

// Multi-tenant safety: all queries are scoped by campaign_id FK (globally unique).
// BelongsToTenant global scope is inactive in queue context, but no cross-tenant
// data can be accessed since CampaignMessage queries filter by specific campaign_id.
class DispatchCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly Campaign $campaign,
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(CampaignService $service): void
    {
        $interactionEvents = app(AgentInteractionEventService::class);
        $interactionId = $interactionEvents->newInteractionId();
        $campaign = $this->campaign->fresh();

        if (! $campaign || ! $campaign->isSending()) {
            Log::info('DispatchCampaignJob: campaign not in sending state, skipping', [
                'interaction_id' => $interactionId,
                'campaign_id' => $this->campaign->id,
                'status' => $campaign?->status,
            ]);

            return;
        }

        CampaignStatusChanged::dispatch($campaign->id, $campaign->status);

        $interactionEvents->record(
            interactionId: $interactionId,
            tenantId: $campaign->tenant_id,
            eventType: 'campaign_dispatch_started',
            eventSource: 'dispatch_campaign_job',
            payload: [
                'campaign_id' => $campaign->id,
            ],
        );

        // Phase 51 — Smart list materialization (D-07 snapshot-on-dispatch)
        if ($campaign->contactList->is_dynamic) {
            $resolver = app(SmartListResolverService::class);
            $count = $resolver->materialize($campaign->contactList);

            $interactionEvents->record(
                interactionId: $interactionId,
                tenantId: $campaign->tenant_id,
                eventType: 'smart_list_materialized',
                eventSource: 'dispatch_campaign_job',
                payload: ['list_id' => $campaign->contactList->id, 'count' => $count],
            );

            if ($count === 0) {
                $campaign->update(['status' => 'completed', 'completed_at' => now()]);
                CampaignStatusChanged::dispatch($campaign->id, 'completed');

                Log::warning('DispatchCampaignJob: smart list resolved to 0 leads, campaign auto-completed', [
                    'interaction_id' => $interactionId,
                    'campaign_id' => $campaign->id,
                    'list_id' => $campaign->contactList->id,
                ]);

                return;
            }

            Log::info('DispatchCampaignJob: smart list materialized', [
                'interaction_id' => $interactionId,
                'campaign_id' => $campaign->id,
                'list_id' => $campaign->contactList->id,
                'entries' => $count,
            ]);
        }

        $index = 0;
        $stopped = false;

        // Stream entries with chunkById to avoid loading 100k+ IDs into memory at once.
        $campaign->contactList->entries()
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($entries) use ($campaign, $service, $interactionEvents, &$index, &$stopped) {
                $campaign->refresh();

                if (! $campaign->isSending()) {
                    Log::info('DispatchCampaignJob: campaign paused/cancelled mid-dispatch', ['campaign_id' => $campaign->id]);
                    $stopped = true;

                    return false;
                }

                if (! $service->checkDailyLimit($campaign)) {
                    Log::info('DispatchCampaignJob: daily limit reached', ['campaign_id' => $campaign->id]);
                    $stopped = true;

                    return false;
                }

                $chunkIds = $entries->pluck('id')->all();

                // Bulk-query already-processed entries in this chunk only.
                $existing = CampaignMessage::where('campaign_id', $campaign->id)
                    ->whereIn('contact_list_entry_id', $chunkIds)
                    ->pluck('contact_list_entry_id')
                    ->all();
                $existingSet = array_flip($existing);

                foreach ($chunkIds as $entryId) {
                    if (isset($existingSet[$entryId])) {
                        continue;
                    }

                    $message = CampaignMessage::firstOrCreate(
                        ['campaign_id' => $campaign->id, 'contact_list_entry_id' => $entryId],
                        ['status' => 'queued']
                    );

                    $delayMs = $index * $campaign->delay_between_ms;

                    SendCampaignMessageJob::dispatch($message, $interactionEvents->newInteractionId())
                        ->delay(now()->addMilliseconds($delayMs));

                    $index++;
                }
            });

        if ($stopped) {
            return;
        }

        if ($index === 0) {
            Log::info('DispatchCampaignJob: no pending entries', ['campaign_id' => $campaign->id]);

            if ($campaign->total_sent >= $campaign->total_recipients && $campaign->total_recipients > 0) {
                $campaign->update(['status' => 'completed', 'completed_at' => now()]);
                CampaignStatusChanged::dispatch($campaign->id, 'completed');
            }

            return;
        }

        Log::info('DispatchCampaignJob: dispatched messages', [
            'interaction_id' => $interactionId,
            'campaign_id' => $campaign->id,
            'count' => $index,
        ]);

        $interactionEvents->record(
            interactionId: $interactionId,
            tenantId: $campaign->tenant_id,
            eventType: 'campaign_dispatch_queued',
            eventSource: 'dispatch_campaign_job',
            payload: [
                'campaign_id' => $campaign->id,
                'queued_count' => $index,
            ],
        );
    }
}
