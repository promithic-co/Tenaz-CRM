<?php

namespace App\Jobs;

use App\Ai\AgentFactory;
use App\Models\FollowupMessage;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\AgentInteractionContext;
use App\Services\AgentInteractionEventService;
use App\Services\AgentService;
use App\Services\ConversationAutomationService;
use App\Services\ConversationContextSynchronizer;
use App\Services\ConversationTimelineService;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\FollowUpSettingsResolver;
use App\Services\FollowUpWindowService;
use App\Services\PauseService;
use App\Services\WhatsappOutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Multi-tenant safety: this job operates on specific Lead/WhatsappInstance instances via FK lookups.
// BelongsToTenant global scope is inactive in queue context (no auth), but no cross-tenant
// data can be accessed because all queries are scoped by specific IDs (agent_id, lead_id).
// FollowupMessage::create() explicitly sets tenant_id from $this->lead->tenant_id.
class ProcessLeadFollowUpJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $timeout = 120;

    public int $maxExceptions = 2;

    // Release the uniqueness lock once processing starts (not at completion), so the
    // next scheduled follow-up can enqueue while this one runs. Covers the retry backoff
    // window [60, 300] with margin so a queued retry can't double-enqueue.
    public int $uniqueFor = 600;

    public function __construct(public Lead $lead)
    {
        $this->onQueue('followups');
    }

    public function uniqueId(): string
    {
        return "followup_{$this->lead->id}";
    }

    public function handle(
        WhatsappOutboxService $outbox,
        FollowUpSettingsResolver $settingsResolver,
        FollowUpWindowService $window,
        PauseService $pause,
        ?ConversationTimelineService $timeline = null,
    ): void {
        $timeline ??= app(ConversationTimelineService::class);

        $interactionEvents = app(AgentInteractionEventService::class);
        $interactionContext = app(AgentInteractionContext::class);
        $interactionId = $interactionEvents->newInteractionId();

        // Check if follow-up is still active (circuit breaker)
        if ($this->lead->followup_status !== 'active') {
            Log::info('ProcessLeadFollowUpJob: skipped (not active)', [
                'interaction_id' => $interactionId,
                'lead_id' => $this->lead->id,
                'status' => $this->lead->followup_status,
            ]);

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $this->lead,
                eventType: 'followup_skipped',
                eventSource: 'process_lead_followup_job',
                payload: ['reason' => 'not_active', 'status' => $this->lead->followup_status],
            );

            return;
        }

        $settings = $settingsResolver->forLead($this->lead);
        $effectiveAiMode = app(ConversationAutomationService::class)
            ->resolveInstanceDefaultedModes(collect([$this->lead]))[$this->lead->id];
        $evaluation = $window->evaluate($this->lead, $settings, now(), $pause, $effectiveAiMode);

        if (! $evaluation['eligible']) {
            Log::info('ProcessLeadFollowUpJob: skipped (not eligible)', [
                'interaction_id' => $interactionId,
                'lead_id' => $this->lead->id,
                'reason' => $evaluation['reason'],
                'window_expires_at' => $evaluation['window_expires_at'],
                'remaining_minutes' => $evaluation['remaining_minutes'],
            ]);

            if (in_array($evaluation['reason'], ['window_expired', 'window_expired_requires_hsm', 'no_inbound_window', 'terminal_status', 'max_reached'], true)) {
                $this->lead->update(['followup_status' => 'inactive']);
            } elseif ($evaluation['reason'] === 'human_paused') {
                $this->lead->update(['followup_status' => 'paused']);
            }

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $this->lead,
                eventType: 'followup_skipped',
                eventSource: 'process_lead_followup_job',
                payload: [
                    'reason' => $evaluation['reason'],
                    'due_at' => $evaluation['due_at'],
                    'window_expires_at' => $evaluation['window_expires_at'],
                    'remaining_minutes' => $evaluation['remaining_minutes'],
                ],
            );

            return;
        }

        // Skip if the client messaged very recently — race guard. Uses last_inbound_at (not
        // last_interaction_at) so scheduler dispatch pre-stamp / outbound activity does not trigger this.
        $skipThresholdMinutes = (int) config('credflow.followup.skip_if_recent_inbound_minutes', 30);
        if (
            $this->lead->last_inbound_at !== null
            && $this->lead->last_inbound_at->diffInMinutes(now(), true) < $skipThresholdMinutes
        ) {
            Log::info('ProcessLeadFollowUpJob: skipped (recent inbound message)', [
                'interaction_id' => $interactionId,
                'lead_id' => $this->lead->id,
                'last_inbound_at' => $this->lead->last_inbound_at,
                'threshold_minutes' => $skipThresholdMinutes,
            ]);

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $this->lead,
                eventType: 'followup_skipped',
                eventSource: 'process_lead_followup_job',
                payload: [
                    'reason' => 'recent_inbound',
                    'last_inbound_at' => $this->lead->last_inbound_at?->toIso8601String(),
                    'threshold_minutes' => $skipThresholdMinutes,
                ],
            );

            return;
        }

        // Resolve the send instance before any LLM work — a lead without a usable
        // instance can never be sent to, so deactivate instead of burning an agent
        // call every dispatch until the window expires. Reversible via reactivate.
        $instance = $this->resolveWhatsappInstance();

        if ($instance === null) {
            Log::warning('follow_up.no_instance_found', [
                'interaction_id' => $interactionId,
                'lead_id' => $this->lead->id,
                'agent_id' => $this->lead->agent_id,
            ]);

            $this->lead->update(['followup_status' => 'inactive']);

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $this->lead,
                eventType: 'followup_skipped',
                eventSource: 'process_lead_followup_job',
                payload: ['reason' => 'no_instance', 'agent_id' => $this->lead->agent_id],
            );

            return;
        }

        Log::info('ProcessLeadFollowUpJob: starting', [
            'interaction_id' => $interactionId,
            'lead_id' => $this->lead->id,
            'lead_nome' => $this->lead->nome,
            'followup_count' => $this->lead->followup_count,
            'attempt' => $this->attempts(),
            'has_conversation' => (bool) $this->lead->conversation_id,
        ]);

        $interactionEvents->recordForLead(
            interactionId: $interactionId,
            lead: $this->lead,
            eventType: 'followup_started',
            eventSource: 'process_lead_followup_job',
            payload: [
                'attempt' => $this->attempts(),
                'followup_count' => $this->lead->followup_count,
                'has_conversation' => (bool) $this->lead->conversation_id,
            ],
        );

        $interactionContext->set([
            'interaction_id' => $interactionId,
            'tenant_id' => (string) $this->lead->tenant_id,
            'lead_id' => $this->lead->id,
            'agent_id' => $this->lead->agent_id,
            'source' => 'followup',
        ]);

        // Per-attempt send claim (F7), mirroring the outbox idempotency guard (F3). Keyed on the
        // attempt number (current count + 1) so a retry that fires after the message was already
        // queued — but before the lead's followup_count is committed — does not send a duplicate.
        $sendClaimKey = "followup_send:{$this->lead->id}:".($this->lead->followup_count + 1);
        if (! Cache::add($sendClaimKey, 1, now()->addMinutes(10))) {
            Log::info('ProcessLeadFollowUpJob: skipped (send already claimed)', [
                'interaction_id' => $interactionId,
                'lead_id' => $this->lead->id,
                'attempt' => $this->lead->followup_count + 1,
            ]);

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $this->lead,
                eventType: 'followup_skipped',
                eventSource: 'process_lead_followup_job',
                payload: ['reason' => 'duplicate_send', 'attempt' => $this->lead->followup_count + 1],
            );

            $interactionContext->clear();

            return;
        }

        try {
            $agent = app(AgentFactory::class)->makeFollowUp($this->lead);
            $instructionPrompt = 'Por favor, gere a mensagem de follow-up agora mesmo.';

            // Resume previous conversation to maintain context, if available.
            // If not, start a new conversation attributed to the lead.
            if ($this->lead->conversation_id) {
                // Mirror un-synced timeline rows into agent memory before resuming —
                // ensures the follow-up sees operator turns and any inbound received
                // while AI was paused, instead of contradicting them.
                $synced = app(ConversationContextSynchronizer::class)->syncPending($this->lead);
                if ($synced > 0) {
                    $interactionEvents->recordForLead(
                        interactionId: $interactionId,
                        lead: $this->lead,
                        eventType: 'context_synced',
                        eventSource: 'process_lead_followup_job',
                        payload: ['rows_synced' => $synced],
                    );
                }

                $reply = $agent->continue($this->lead->conversation_id, $this->lead)->prompt($instructionPrompt);
            } else {
                $reply = $agent->forUser($this->lead)->prompt($instructionPrompt);
            }

            if ($reply->conversationId && $reply->conversationId !== $this->lead->conversation_id) {
                $this->lead->update(['conversation_id' => $reply->conversationId]);
            }

            $text = (string) $reply;

            if (! empty($text) && ! str_contains($text, AgentService::NO_REPLY_SENTINEL)) {
                // Single transaction covering the outbox rows AND the bookkeeping
                // (followup_messages + counter): if any write fails, everything rolls
                // back together — no message can go out without its attempt being
                // recorded (which would re-send on the next tick). The outbox job
                // dispatch is afterCommit-aware, so it only fires on commit.
                $outboxMessages = DB::transaction(function () use ($outbox, $instance, $interactionId, $settings, $text): array {
                    $outboxMessages = $outbox->queueSplitTextForLead(
                        lead: $this->lead,
                        instance: $instance,
                        phone: $this->lead->whatsapp,
                        text: $text,
                        source: 'followup',
                        senderType: 'agent',
                        interactionId: $interactionId,
                    );

                    FollowupMessage::create([
                        'lead_id' => $this->lead->id,
                        'tenant_id' => $this->lead->tenant_id,
                        'attempt' => $this->lead->followup_count + 1,
                        'message_text' => $text,
                        'tone' => $settings['tone'] ?? null,
                        'sent_at' => now(),
                        'status' => 'sent',
                    ]);

                    $this->lead->increment('followup_count');
                    $this->lead->update(['last_interaction_at' => now()]);

                    return $outboxMessages;
                });

                $this->lead->refresh();

                foreach ($outboxMessages as $outboxMessage) {
                    if ($outboxMessage->timelineMessage) {
                        $timeline->broadcast($outboxMessage->timelineMessage);
                    }
                }

                Log::info('ProcessLeadFollowUpJob: message sent', [
                    'interaction_id' => $interactionId,
                    'lead_id' => $this->lead->id,
                    'followup_count' => $this->lead->followup_count,
                    'message_preview' => mb_substr($text, 0, 80),
                    'instance' => $instance->name,
                ]);

                $interactionEvents->recordForLead(
                    interactionId: $interactionId,
                    lead: $this->lead,
                    eventType: 'outbound_queued',
                    eventSource: 'process_lead_followup_job',
                    payload: [
                        'source' => 'followup',
                        'instance_name' => $instance->name,
                        'response_length' => mb_strlen($text),
                        'followup_count' => $this->lead->followup_count,
                    ],
                );

                $maxCount = (int) ($settings['max_attempts_within_window'] ?? 2);

                if ($this->lead->followup_count >= $maxCount) {
                    $this->lead->update(['followup_status' => 'inactive']);

                    Log::info('ProcessLeadFollowUpJob: lead deactivated (max reached)', [
                        'lead_id' => $this->lead->id,
                        'final_count' => $this->lead->followup_count,
                        'max_count' => $maxCount,
                    ]);
                }

                app(DashboardMetricsService::class)->dispatchUpdate((string) $this->lead->tenant_id);
            } else {
                Log::warning('ProcessLeadFollowUpJob: empty or sentinel reply', [
                    'interaction_id' => $interactionId,
                    'lead_id' => $this->lead->id,
                    'text_length' => mb_strlen($text),
                ]);

                // Record no-reply attempt so nextDueAt enforces min_interval_minutes backoff
                // instead of re-firing every cron tick on persistent agent failures.
                // followup_count is NOT incremented — this attempt does not count toward max.
                FollowupMessage::create([
                    'lead_id' => $this->lead->id,
                    'tenant_id' => $this->lead->tenant_id,
                    'attempt' => $this->lead->followup_count + 1,
                    'message_text' => '',
                    'tone' => $settings['tone'] ?? null,
                    'sent_at' => now(),
                    'status' => 'no_reply',
                ]);

                $interactionEvents->recordForLead(
                    interactionId: $interactionId,
                    lead: $this->lead,
                    eventType: 'agent_no_reply',
                    eventSource: 'process_lead_followup_job',
                    payload: ['reason' => 'empty_or_sentinel', 'text_length' => mb_strlen($text)],
                );
            }

        } finally {
            $interactionContext->clear();
        }
    }

    private function resolveWhatsappInstance(): ?WhatsappInstance
    {
        if ($this->lead->whatsapp_instance_id === null) {
            return null;
        }

        return WhatsappInstance::withoutGlobalScopes()
            ->where('tenant_id', $this->lead->tenant_id)
            ->whereKey($this->lead->whatsapp_instance_id)
            ->first();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessLeadFollowUpJob: permanently failed', [
            'lead_id' => $this->lead->id,
            'lead_nome' => $this->lead->nome,
            'followup_count' => $this->lead->followup_count,
            'error' => $exception->getMessage(),
            'trace' => mb_substr($exception->getTraceAsString(), 0, 500),
        ]);

        try {
            // Backoff floor: nextDueAt() reads the latest followup_messages row, so this
            // 'failed' record defers the next attempt by min_interval_minutes instead of
            // letting the cron re-dispatch (with 3 tries each) on every tick.
            // followup_count is NOT incremented — the failure does not count toward max.
            FollowupMessage::create([
                'lead_id' => $this->lead->id,
                'tenant_id' => $this->lead->tenant_id,
                'attempt' => $this->lead->followup_count + 1,
                'message_text' => '',
                'tone' => null,
                'sent_at' => now(),
                'status' => 'failed',
            ]);

            $interactionEvents = app(AgentInteractionEventService::class);
            $interactionEvents->recordForLead(
                interactionId: $interactionEvents->newInteractionId(),
                lead: $this->lead,
                eventType: 'followup_failed',
                eventSource: 'process_lead_followup_job',
                payload: [
                    'attempt' => $this->lead->followup_count + 1,
                    'error' => mb_substr($exception->getMessage(), 0, 500),
                ],
            );
        } catch (\Throwable $bookkeepingError) {
            Log::error('ProcessLeadFollowUpJob: failed-handler bookkeeping error', [
                'lead_id' => $this->lead->id,
                'error' => $bookkeepingError->getMessage(),
            ]);
        }
    }
}
