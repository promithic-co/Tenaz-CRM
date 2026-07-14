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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        // Phase 51 — Smart list materialization (D-07 snapshot-on-dispatch). Materialize only on
        // the first dispatch: a revive/resume run (CAMP-04) re-enters this job on a later day, and
        // re-resolving a dynamic list would silently swap the frozen audience mid-campaign. Once
        // any message row exists the fan-out has begun, so the materialized entries plus those rows
        // are the snapshot and every later run reuses them.
        $alreadyFannedOut = CampaignMessage::where('campaign_id', $campaign->id)->exists();

        if ($campaign->contactList->is_dynamic && ! $alreadyFannedOut) {
            $resolver = app(SmartListResolverService::class);
            $count = $resolver->materialize($campaign->contactList);

            // total_recipients was set in CampaignService::start() from the entries count BEFORE the
            // dynamic list was resolved, so it was 0/stale for every smart-list campaign — corrupting
            // sentPercent, the Show funnel, and the broadcast 'pending' math. Correct it to the
            // materialized count now that the snapshot exists (CAMP-06).
            $campaign->update(['total_recipients' => $count]);

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

        // Daily budget (CAMP-04): enqueue at most today's remaining allowance instead of the
        // whole list. The old per-chunk boolean gate compared sent-today (≈0 at fan-out time)
        // against the limit, so the entire list passed in a single run and nothing ever
        // re-dispatched the remainder. Enforcement now happens here (enqueue within budget)
        // with a send-time safety net in SendCampaignMessageJob; the remainder stays
        // undispatched for the monitor revive to pick up on the next day's budget.
        $dailyBudget = $service->remainingDailyBudget($campaign);

        if ($dailyBudget <= 0) {
            Log::info('DispatchCampaignJob: no daily budget remaining, deferring to monitor revive', [
                'campaign_id' => $campaign->id,
            ]);

            return;
        }

        $index = 0;
        $stopped = false;
        $budgetExhausted = false;

        // Stream entries with chunkById to avoid loading 100k+ IDs into memory at once.
        $campaign->contactList->entries()
            ->select(['id', 'opt_in_status'])
            ->orderBy('id')
            ->chunkById(500, function ($entries) use ($campaign, $interactionEvents, $dailyBudget, &$index, &$stopped, &$budgetExhausted) {
                $campaign->refresh();

                if (! $campaign->isSending()) {
                    Log::info('DispatchCampaignJob: campaign paused/cancelled mid-dispatch', ['campaign_id' => $campaign->id]);
                    $stopped = true;

                    return false;
                }

                $chunkIds = $entries->pluck('id')->all();

                // Suppress opted-out recipients before enqueueing any send. Consent is
                // enforced here (pre-dispatch), never after a provider failure, so we
                // never spend a Meta send on a contact who has opted out.
                $optedOutSet = array_flip(
                    $entries->where('opt_in_status', 'opted_out')->pluck('id')->all()
                );

                // Bulk-load rows already created for this chunk. A 'pending' row with no
                // provider attempt is an orphan: its queue job was consumed while the campaign
                // was paused (SendCampaignMessageJob parks it there on the paused-skip path),
                // so resume must re-enqueue it. Every other existing row — queued (live delayed
                // job), sent, failed, in_doubt, or already provider-attempted — is skipped.
                $existingByEntry = CampaignMessage::where('campaign_id', $campaign->id)
                    ->whereIn('contact_list_entry_id', $chunkIds)
                    ->get(['id', 'contact_list_entry_id', 'status', 'provider_attempted_at'])
                    ->keyBy('contact_list_entry_id');

                foreach ($chunkIds as $entryId) {
                    if ($index >= $dailyBudget) {
                        $budgetExhausted = true;
                        break;
                    }

                    if (isset($optedOutSet[$entryId])) {
                        continue;
                    }

                    $message = $existingByEntry->get($entryId);

                    if ($message !== null) {
                        if ($message->status !== 'pending' || $message->provider_attempted_at !== null) {
                            continue;
                        }

                        $message->update(['status' => 'queued']);
                    } else {
                        $message = CampaignMessage::firstOrCreate(
                            ['campaign_id' => $campaign->id, 'contact_list_entry_id' => $entryId],
                            ['status' => 'queued']
                        );
                    }

                    // The send slot doubles as the retry-window anchor (scheduledFor): a
                    // deadline counted from dispatch would already be expired when a far-tail
                    // staggered message first pops, failing it before it ever runs.
                    $sendAt = now()->addMilliseconds($index * $campaign->delay_between_ms);

                    SendCampaignMessageJob::dispatch($message, $interactionEvents->newInteractionId(), $sendAt)
                        ->delay($sendAt);

                    $index++;
                }

                if ($budgetExhausted) {
                    Log::info('DispatchCampaignJob: daily budget exhausted, monitor revives on the next day', [
                        'campaign_id' => $campaign->id,
                        'enqueued' => $index,
                    ]);

                    return false;
                }
            });

        if ($stopped || $budgetExhausted) {
            return;
        }

        if ($index === 0) {
            Log::info('DispatchCampaignJob: no pending entries', ['campaign_id' => $campaign->id]);

            // Single source of truth for completion (CAMP-03): the old counter predicate
            // (total_sent >= total_recipients) never closed a campaign with a failure or an
            // opt-out skip. checkAndComplete re-evaluates work exhaustion under a row lock.
            $service->checkAndComplete($campaign);

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

    /**
     * Terminal signal (REL-4 / SCALE-11): tries=1 means a mid-fan-out crash leaves the
     * campaign in `sending` with only part of the list enqueued and no retry. Emit an
     * actionable breadcrumb so the stall is observable, then auto-resume the remainder —
     * re-dispatch is idempotent (firstOrCreate skips already-enqueued entries), bounded by
     * a per-campaign budget so a deterministic crash cannot loop forever.
     */
    public function failed(Throwable $e): void
    {
        Log::error('DispatchCampaignJob.failed', [
            'campaign_id' => $this->campaign->id,
            'error' => $e->getMessage(),
        ]);

        $interactionEvents = app(AgentInteractionEventService::class);
        $interactionEvents->record(
            interactionId: $interactionEvents->newInteractionId(),
            tenantId: $this->campaign->tenant_id,
            eventType: 'campaign_dispatch_failed',
            eventSource: 'dispatch_campaign_job',
            payload: ['campaign_id' => $this->campaign->id, 'error' => $e->getMessage()],
            severity: 'error',
        );

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

        $campaign = $this->campaign->fresh();

        if (! $campaign || ! $campaign->isSending()) {
            return;
        }

        $key = "campaign_dispatch_resume:{$campaign->id}";
        $used = (int) Cache::get($key, 0);

        if ($used >= $max) {
            Log::error('DispatchCampaignJob.failed: auto-resume budget exhausted, manual resume required', [
                'campaign_id' => $campaign->id,
                'attempts' => $used,
            ]);

            return;
        }

        Cache::put($key, $used + 1, now()->addHours(6));

        $delay = (int) config('credflow.campaigns.dispatch_redispatch_delay_seconds', 10);
        self::dispatch($campaign)->delay(now()->addSeconds($delay));

        Log::warning('DispatchCampaignJob.failed: auto-resuming remainder', [
            'campaign_id' => $campaign->id,
            'attempt' => $used + 1,
            'max' => $max,
        ]);
    }
}
