<?php

namespace App\Services;

use App\Events\CampaignStatusChanged;
use App\Exceptions\CampaignConfigurationException;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(
        private readonly CampaignTemplateCompatibility $compatibility,
    ) {}

    /**
     * Start a campaign. Guard: must be draft or scheduled, template must be APPROVED.
     */
    public function start(Campaign $campaign): void
    {
        $totalRecipients = $campaign->contactList?->entries()->count() ?? 0;

        // Concurrency guard (DB-5): lock the row, re-evaluate the guard against the locked
        // state, then transition — so a control action racing the dispatcher or another
        // control action cannot both pass canStart() and clobber each other (last-writer-wins).
        DB::transaction(function () use ($campaign, $totalRecipients): void {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->canStart()) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser iniciada (status: {$campaign->status}).");
            }

            $this->validatedSendConfig($locked);

            if (! $locked->whatsappTemplate?->isApproved()) {
                throw new \RuntimeException('O template da campanha não está aprovado.');
            }

            $locked->update([
                'status' => 'sending',
                'started_at' => now(),
                'paused_at' => null,
                'total_recipients' => $totalRecipients,
            ]);

            $campaign->setRawAttributes($locked->getAttributes());
        });

        Log::info('CampaignService.start', ['campaign_id' => $campaign->id, 'recipients' => $totalRecipients]);

        DispatchCampaignJob::dispatch($campaign);
    }

    /**
     * Pause a running campaign.
     */
    public function pause(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->canPause()) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser pausada (status: {$campaign->status}).");
            }

            $locked->update([
                'status' => 'paused',
                'paused_at' => now(),
            ]);

            $campaign->setRawAttributes($locked->getAttributes());
        });

        Log::info('CampaignService.pause', ['campaign_id' => $campaign->id]);
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->canResume()) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser retomada (status: {$campaign->status}).");
            }

            $this->validatedSendConfig($locked);

            $locked->update([
                'status' => 'sending',
                'paused_at' => null,
            ]);

            $campaign->setRawAttributes($locked->getAttributes());
        });

        Log::info('CampaignService.resume', ['campaign_id' => $campaign->id]);

        DispatchCampaignJob::dispatch($campaign);
    }

    public function validatedSendConfig(
        Campaign $campaign,
        ?WhatsappInstance $instance = null,
        ?WhatsappTemplate $template = null,
    ): CampaignSendConfig {
        $instance ??= $campaign->whatsappInstance()->first();
        $template ??= $campaign->whatsappTemplate()->first();

        if (! $instance || ! $template) {
            throw new CampaignConfigurationException(['NO_INSTANCE_OR_TEMPLATE']);
        }

        $config = CampaignSendConfig::fromModels($campaign, $instance, $template);
        $violations = $this->compatibility->violationsForConfig($config);

        if ($violations !== []) {
            throw new CampaignConfigurationException($violations);
        }

        return $config;
    }

    public function pauseForConfigurationViolation(
        Campaign $campaign,
        CampaignConfigurationException $exception,
    ): bool {
        return DB::transaction(function () use ($campaign, $exception): bool {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->isSending()) {
                return false;
            }

            $reason = $exception->primaryViolation();
            $locked->update([
                'status' => 'paused',
                'paused_at' => now(),
                'paused_from_status' => 'sending',
                'pause_reason_code' => $reason,
                'failure_reason' => "Invalid campaign send configuration: {$reason}",
            ]);

            $campaign->setRawAttributes($locked->getAttributes());

            Log::warning('CampaignService.configuration_pause', [
                'campaign_id' => $locked->id,
                'reason_code' => $reason,
            ]);

            return true;
        });
    }

    public function pauseAndFailForConfigurationViolation(
        Campaign $campaign,
        CampaignMessage $message,
        CampaignConfigurationException $exception,
    ): bool {
        return DB::transaction(function () use ($campaign, $message, $exception): bool {
            $lockedCampaign = Campaign::query()
                ->whereKey($campaign->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedCampaign) {
                return false;
            }

            $lockedMessage = CampaignMessage::query()
                ->whereKey($message->getKey())
                ->where('campaign_id', $lockedCampaign->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedMessage) {
                return false;
            }

            $messageCanFail = in_array($lockedMessage->status, ['pending', 'queued'], true)
                && $lockedMessage->provider_attempted_at === null;

            if (! $lockedCampaign->isSending()) {
                if ($messageCanFail) {
                    $lockedMessage->update(['status' => 'pending']);
                }

                return false;
            }

            $reason = $exception->primaryViolation();
            $lockedCampaign->update([
                'status' => 'paused',
                'paused_at' => now(),
                'paused_from_status' => 'sending',
                'pause_reason_code' => $reason,
                'failure_reason' => "Invalid campaign send configuration: {$reason}",
            ]);

            $messageFailed = $messageCanFail;

            if ($messageFailed) {
                $lockedMessage->markFailed($reason, $exception->getMessage());
            }

            $campaign->setRawAttributes($lockedCampaign->getAttributes());

            Log::warning('CampaignService.configuration_pause_with_message_failure', [
                'campaign_id' => $lockedCampaign->id,
                'message_id' => $lockedMessage->id,
                'reason_code' => $reason,
                'message_failed' => $messageFailed,
            ]);

            return true;
        });
    }

    /**
     * Create a fresh draft copy of a campaign, carrying over the audience, template,
     * instance and throttle configuration but none of the send history or counters.
     * Lets a user re-run a finished (or any) campaign without rebuilding it by hand.
     */
    public function duplicate(Campaign $campaign): Campaign
    {
        return Campaign::create([
            'tenant_id' => $campaign->tenant_id,
            'whatsapp_instance_id' => $campaign->whatsapp_instance_id,
            'contact_list_id' => $campaign->contact_list_id,
            'whatsapp_template_id' => $campaign->whatsapp_template_id,
            'name' => Str::limit($campaign->name, 240, '').' (cópia)',
            'template_params_mapping' => $campaign->template_params_mapping,
            'daily_limit' => $campaign->daily_limit,
            'delay_between_ms' => $campaign->delay_between_ms,
            'error_threshold_percent' => $campaign->error_threshold_percent,
            'status' => 'draft',
        ]);
    }

    /**
     * Update the live throttle knobs of a campaign. Editable in any pre-terminal state
     * (draft/scheduled/paused/sending) so an operator can slow down or widen a running
     * or paused campaign without recreating it. Immutable references (list/template/
     * instance) are intentionally out of scope — those go through the guarded update path.
     *
     * @param  array{daily_limit: int, delay_between_ms: int, error_threshold_percent: int}  $attributes
     */
    public function updateThrottle(Campaign $campaign, array $attributes): void
    {
        if (in_array($campaign->status, ['completed', 'cancelled'], true)) {
            throw new \RuntimeException("Campanha '{$campaign->name}' não permite editar limites (status: {$campaign->status}).");
        }

        $campaign->update([
            'daily_limit' => $attributes['daily_limit'],
            'delay_between_ms' => $attributes['delay_between_ms'],
            'error_threshold_percent' => $attributes['error_threshold_percent'],
        ]);

        Log::info('CampaignService.update_throttle', ['campaign_id' => $campaign->id]);
    }

    /**
     * Reprocess every failed recipient of a campaign: reset the failed rows back to a
     * fresh 'pending' state and re-dispatch. A paused or completed campaign is reopened
     * to 'sending' so the revived rows actually send. Returns the number of rows revived.
     *
     * Only rows with no sent_at are eligible: a 'failed' row that DOES carry sent_at is a
     * post-delivery webhook failure (the send already reached the contact), so re-sending
     * it would duplicate. in_doubt rows are also excluded — they are ambiguous by design
     * (may have reached the contact) and are owned by the reconciliation timeout.
     */
    public function reprocessFailures(Campaign $campaign): int
    {
        $reset = DB::transaction(function () use ($campaign): int {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, ['sending', 'paused', 'completed'], true)) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode reprocessar falhas (status: {$campaign->status}).");
            }

            $count = $this->reviveFailedMessages($locked, null);

            $this->reopenForRevival($locked, $count);

            $campaign->setRawAttributes($locked->getAttributes());

            return $count;
        });

        if ($reset > 0) {
            Log::info('CampaignService.reprocess_failures', ['campaign_id' => $campaign->id, 'revived' => $reset]);

            DispatchCampaignJob::dispatch($campaign);
        }

        return $reset;
    }

    /**
     * Retry a single failed recipient. Same safety envelope as reprocessFailures but scoped
     * to one message. Returns true when the row was revived and a dispatch was queued.
     */
    public function retryMessage(Campaign $campaign, CampaignMessage $message): bool
    {
        if ($message->campaign_id !== $campaign->id) {
            return false;
        }

        $reset = DB::transaction(function () use ($campaign, $message): int {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, ['sending', 'paused', 'completed'], true)) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode reenviar mensagens (status: {$campaign->status}).");
            }

            $count = $this->reviveFailedMessages($locked, $message->getKey());

            $this->reopenForRevival($locked, $count);

            $campaign->setRawAttributes($locked->getAttributes());

            return $count;
        });

        if ($reset > 0) {
            Log::info('CampaignService.retry_message', ['campaign_id' => $campaign->id, 'message_id' => $message->id]);

            DispatchCampaignJob::dispatch($campaign);
        }

        return $reset > 0;
    }

    /**
     * Remove recipients from the disparo before they send. Marks the selected still-pending
     * rows as 'skipped' (terminal, consent-neutral) with a distinct REMOVED_MANUAL code, so
     * the fan-out and any live queue job stop sending to them. Rows already claimed by the
     * provider (provider_attempted_at set) are left untouched to preserve the in-doubt guard.
     *
     * @param  list<int>  $messageIds
     */
    public function removeRecipients(Campaign $campaign, array $messageIds): int
    {
        if ($messageIds === []) {
            return 0;
        }

        $removed = CampaignMessage::where('campaign_id', $campaign->id)
            ->whereIn('id', $messageIds)
            ->whereIn('status', ['pending', 'queued'])
            ->whereNull('provider_attempted_at')
            ->update([
                'status' => 'skipped',
                'error_code' => 'REMOVED_MANUAL',
                'error_message' => 'Removido do disparo manualmente.',
                'provider_attempt_token' => null,
                'provider_attempt_lease_expires_at' => null,
                'provider_retry_not_before' => null,
            ]);

        if ($removed > 0) {
            Log::info('CampaignService.remove_recipients', ['campaign_id' => $campaign->id, 'removed' => $removed]);
        }

        return $removed;
    }

    /**
     * Reset failed (never-sent) message rows to a clean pending state, ready for re-dispatch.
     */
    private function reviveFailedMessages(Campaign $campaign, ?int $onlyMessageId): int
    {
        $query = CampaignMessage::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->whereNull('sent_at');

        if ($onlyMessageId !== null) {
            $query->whereKey($onlyMessageId);
        }

        return $query->update([
            'status' => 'pending',
            'provider_attempted_at' => null,
            'provider_attempt_token' => null,
            'provider_attempt_lease_expires_at' => null,
            'provider_retry_not_before' => null,
            'error_code' => null,
            'error_subcode' => null,
            'provider_error_code' => null,
            'provider_http_status' => null,
            'provider_error_type' => null,
            'provider_error_trace_id' => null,
            'error_message' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * Reopen a paused/completed campaign to 'sending' when a revival actually reset rows.
     */
    private function reopenForRevival(Campaign $locked, int $revivedCount): void
    {
        if ($revivedCount > 0 && in_array($locked->status, ['paused', 'completed'], true)) {
            $locked->update([
                'status' => 'sending',
                'paused_at' => null,
                'completed_at' => null,
                'failure_reason' => null,
                'pause_reason_code' => null,
                'paused_from_status' => null,
            ]);
        }
    }

    /**
     * Cancel a sending or paused campaign.
     */
    public function cancel(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, ['sending', 'paused'], true)) {
                throw new \RuntimeException("Campanha '{$campaign->name}' não pode ser cancelada (status: {$campaign->status}).");
            }

            $locked->update([
                'status' => 'cancelled',
                'failure_reason' => 'Cancelada manualmente',
            ]);

            $campaign->setRawAttributes($locked->getAttributes());
        });

        Log::info('CampaignService.cancel', ['campaign_id' => $campaign->id]);
    }

    /**
     * Check if the failure rate exceeds the threshold, and auto-pause if so.
     * Wallet errors (error_code 1003) are owned by MonitorCampaignsCommand,
     * which scans for them on a schedule — kept out of this hot per-failure
     * path to avoid an extra query on every failed message.
     * Returns true if the campaign was paused.
     */
    public function checkAndAutoPause(Campaign $campaign): bool
    {
        // Scale guard (SCALE-1): collapse the per-failure auto-pause convoy. This check is
        // invoked from every failure branch of SendCampaignMessageJob, so a failure storm had
        // up to one concurrent send worker per process all taking an exclusive lockForUpdate on
        // the single campaign row. A cheap atomic debounce gate lets only the first caller per
        // short window reach the locked evaluation below; the rest skip it. MonitorCampaignsCommand
        // is the timer-based backstop for any failure that loses the gate race. Fail open on a
        // cache outage so a degraded cache can never silently disable the safety control.
        $debounceSeconds = (int) config('credflow.campaigns.autopause_debounce_seconds', 3);

        if ($debounceSeconds > 0) {
            try {
                $wonGate = Cache::add("campaign:autopause-gate:{$campaign->getKey()}", 1, $debounceSeconds);
            } catch (\Throwable $e) {
                $wonGate = true;
            }

            if (! $wonGate) {
                return false;
            }
        }

        // Concurrency guard (DB-5): evaluate + pause against a locked row so the auto-pause
        // safety control cannot be clobbered by a racing resume (which would let a campaign
        // keep sending past its failure threshold and keep burning Meta reputation).
        return DB::transaction(function () use ($campaign): bool {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->isSending()) {
                return false;
            }

            // Check if we have enough sent messages to evaluate threshold
            if ($locked->total_sent < 10) {
                return false;
            }

            if ($locked->failureRate() > $locked->error_threshold_percent) {
                $locked->update([
                    'status' => 'paused',
                    'paused_at' => now(),
                    'failure_reason' => "Taxa de falha ({$locked->failureRate()}%) excedeu o limite ({$locked->error_threshold_percent}%).",
                ]);

                Log::warning('CampaignService.auto_pause_threshold', [
                    'campaign_id' => $locked->id,
                    'failure_rate' => $locked->failureRate(),
                ]);

                $campaign->setRawAttributes($locked->getAttributes());

                return true;
            }

            return false;
        });
    }

    /**
     * Complete a campaign whose work is exhausted (CAMP-03). Complete = no message row is
     * still actionable (pending/queued — in flight or re-enqueueable) AND every dispatchable
     * entry already has a row (fan-out finished; opted-out entries never get a row by design).
     * The old counter predicate (total_sent >= total_recipients) could never close a campaign
     * with a single failure or opt-out skip. in_doubt rows do not block: they are terminal by
     * design (never re-sent) and a late webhook still upgrades their counters after completion.
     * Same locked-row shape as the other transitions (DB-5) so a racing pause/cancel wins.
     * Returns true if the campaign was completed.
     */
    public function checkAndComplete(Campaign $campaign): bool
    {
        return DB::transaction(function () use ($campaign): bool {
            $locked = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->first();

            if (! $locked || ! $locked->isSending()) {
                return false;
            }

            $hasActionableMessages = CampaignMessage::where('campaign_id', $locked->id)
                ->whereIn('status', ['pending', 'queued'])
                ->exists();

            if ($hasActionableMessages) {
                return false;
            }

            $hasUndispatchedEntries = (bool) $locked->contactList
                ?->entries()
                ->where('opt_in_status', '!=', 'opted_out')
                ->whereNotIn('id', CampaignMessage::where('campaign_id', $locked->id)->select('contact_list_entry_id'))
                ->exists();

            if ($hasUndispatchedEntries) {
                return false;
            }

            $locked->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $campaign->setRawAttributes($locked->getAttributes());

            CampaignStatusChanged::dispatch($locked->id, 'completed');

            Log::info('CampaignService.complete', ['campaign_id' => $locked->id]);

            return true;
        });
    }

    /**
     * Check if the campaign is under its daily send limit.
     * Returns true if under the limit (can continue sending).
     */
    public function checkDailyLimit(Campaign $campaign): bool
    {
        // Sargable range (SCALE-6): whereDate() wraps the column in a function and cannot use
        // an index — a full scan on every 500-entry chunk of the dispatcher. The explicit
        // [start-of-day, end-of-day] range is index-friendly against (campaign_id, sent_at)
        // and implicitly excludes null sent_at.
        $sentToday = CampaignMessage::where('campaign_id', $campaign->id)
            ->whereBetween('sent_at', [today()->startOfDay(), today()->endOfDay()])
            ->count();

        return $sentToday < $campaign->daily_limit;
    }

    /**
     * Remaining sends allowed today (CAMP-04): daily_limit minus messages already sent today
     * minus rows currently queued (live jobs that consume budget when they pop). Parked
     * 'pending' rows do not count — they only send again once a revive re-enqueues them
     * inside a future day's budget. The "day" is the application timezone.
     */
    public function remainingDailyBudget(Campaign $campaign): int
    {
        $sentToday = CampaignMessage::where('campaign_id', $campaign->id)
            ->whereBetween('sent_at', [today()->startOfDay(), today()->endOfDay()])
            ->count();

        $inFlight = CampaignMessage::where('campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->count();

        return max(0, $campaign->daily_limit - $sentToday - $inFlight);
    }
}
