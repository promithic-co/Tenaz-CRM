<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ConversationSession;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRM-first ingest pipeline: persists an inbound conversation message
 * (lead + timeline + audit) inside a single transaction, independent of
 * AI / automation / outbox concerns. Idempotent on provider_message_id so
 * webhook retries do not duplicate rows.
 */
class IncomingConversationPersister
{
    public function __construct(
        private readonly ConversationTimelineService $timeline,
        private readonly AgentInteractionEventService $events,
        private readonly CampaignReplyDetector $campaignDetector,
        private readonly ConversationAutomationService $automation,
        private readonly ContactSyncService $contactSync,
        private readonly CampaignConversationTimelineWriter $campaignTimeline,
        private readonly ConversationSessionLifecycleService $sessions,
    ) {}

    /**
     * @param  array<string, mixed>|null  $mediaContext
     * @param  array<string, mixed>|null  $referral
     * @return array{lead: Lead, timelineMessage: ConversationTimelineMessage, mode: string, duplicate: bool}|null
     *                                                                                                             null when lock contention forces the caller to release the job.
     */
    public function persist(
        string $tenantId,
        ?int $agentId,
        string $phone,
        string $name,
        string $instanceName,
        string $aggregatedMessage,
        ?array $mediaContext,
        string $interactionId,
        ?string $providerMessageId,
        ?array $referral = null,
    ): ?array {
        // Serialize same-provider-id webhook redeliveries across workers so the
        // existence-check + insert in persistGuarded() is atomic. Without this,
        // two concurrent redeliveries of one inbound both pass the dup check at
        // :50 and double-insert the timeline row AND double-fire campaign
        // detection + automation (DB-3). The Redis-backed lock is distributed;
        // an inbound with no provider id cannot be deduped and falls through.
        if ($providerMessageId === null) {
            return $this->persistGuarded($tenantId, $agentId, $phone, $name, $instanceName, $aggregatedMessage, $mediaContext, $interactionId, $providerMessageId, $referral);
        }

        $providerLockKey = "inbound_persist_{$tenantId}_{$providerMessageId}";

        try {
            return Cache::lock($providerLockKey, 15)->block(8, fn (): ?array => $this->persistGuarded($tenantId, $agentId, $phone, $name, $instanceName, $aggregatedMessage, $mediaContext, $interactionId, $providerMessageId, $referral));
        } catch (LockTimeoutException) {
            Log::warning('whatsapp_persister.provider_lock_timeout', [
                'interaction_id' => $interactionId,
                'provider_message_id' => $providerMessageId,
                'tenant_id' => $tenantId,
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $mediaContext
     * @param  array<string, mixed>|null  $referral
     * @return array{lead: Lead, timelineMessage: ConversationTimelineMessage, mode: string, duplicate: bool}|null
     *                                                                                                             null when lock contention forces the caller to release the job.
     */
    private function persistGuarded(
        string $tenantId,
        ?int $agentId,
        string $phone,
        string $name,
        string $instanceName,
        string $aggregatedMessage,
        ?array $mediaContext,
        string $interactionId,
        ?string $providerMessageId,
        ?array $referral = null,
    ): ?array {
        // Fast-path idempotency: webhook retries with a known provider id must
        // never duplicate timeline rows or fire campaign / automation again.
        if ($providerMessageId !== null) {
            $existingMessage = ConversationTimelineMessage::query()
                ->where('tenant_id', (string) $tenantId)
                ->where('provider_message_id', $providerMessageId)
                ->where('direction', 'inbound')
                ->first();

            if ($existingMessage) {
                $lead = Lead::find($existingMessage->lead_id);
                if ($lead) {
                    Log::info('whatsapp_persister.duplicate_provider_message', [
                        'interaction_id' => $interactionId,
                        'provider_message_id' => $providerMessageId,
                        'lead_id' => $lead->id,
                    ]);

                    return [
                        'lead' => $lead,
                        'timelineMessage' => $existingMessage,
                        'mode' => $this->automation->resolveMode($lead, $instanceName),
                        'duplicate' => true,
                    ];
                }
            }
        }

        $instanceId = $instanceName !== ''
            ? WhatsappInstance::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('name', $instanceName)
                ->value('id')
            : null;

        $lockKey = "lead_create_{$tenantId}_{$phone}";

        try {
            $lead = Cache::lock($lockKey, 8)->block(5, function () use ($tenantId, $agentId, $phone, $name, $instanceName, $instanceId, $interactionId): Lead {
                return DB::transaction(function () use ($tenantId, $agentId, $phone, $name, $instanceName, $instanceId, $interactionId): Lead {
                    $existing = Lead::query()
                        ->where('tenant_id', $tenantId)
                        ->where('whatsapp', $phone)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $updates = [];
                        if ($agentId !== null && $existing->agent_id !== $agentId) {
                            $updates['agent_id'] = $agentId;

                            if ($existing->agent_id !== null) {
                                Log::info('whatsapp_persister.lead_agent_switched', [
                                    'interaction_id' => $interactionId,
                                    'lead_id' => $existing->id,
                                    'previous_agent_id' => $existing->agent_id,
                                    'new_agent_id' => $agentId,
                                    'instance_name' => $instanceName,
                                ]);
                            }
                        }
                        if ($instanceId !== null && $existing->whatsapp_instance_id !== $instanceId) {
                            $updates['whatsapp_instance_id'] = $instanceId;
                        }
                        if (! $existing->nome && $name) {
                            $updates['nome'] = $name;
                        }
                        if (! empty($updates)) {
                            $existing->update($updates);
                        }

                        return $existing;
                    }

                    return Lead::create([
                        'tenant_id' => $tenantId,
                        'agent_id' => $agentId,
                        'whatsapp' => $phone,
                        'modo' => 'receptivo',
                        'nome' => $name ?: null,
                        'whatsapp_instance_id' => $instanceId,
                    ]);
                });
            });
        } catch (LockTimeoutException) {
            Log::warning('whatsapp_persister.lead_lock_timeout', [
                'interaction_id' => $interactionId,
                'phone' => $phone,
                'tenant_id' => $tenantId,
            ]);

            return null;
        }

        // Captured before any refresh()/detect() so the campaign-template backfill only runs
        // for a lead born on this very inbound (not a returning contact).
        $leadWasCreated = $lead->wasRecentlyCreated;

        // CRM-first: link the inbound lead to its canonical Contact. Runs outside the
        // lead-create transaction (own cache lock + writes) and must never hide the
        // message — a sync failure is logged, not propagated.
        try {
            $this->contactSync->syncFromLead($lead, Contact::SOURCE_WHATSAPP_INBOUND);
            $lead->refresh();
        } catch (\Throwable $e) {
            Log::warning('whatsapp_persister.contact_sync_failed', [
                'interaction_id' => $interactionId,
                'lead_id' => $lead->id,
                'message' => $e->getMessage(),
            ]);
        }

        $this->events->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'inbound_received',
            eventSource: 'incoming_conversation_persister',
            payload: [
                'phone' => $phone,
                'name' => $name,
                'instance_name' => $instanceName,
                'provider_message_id' => $providerMessageId,
                'message_length' => strlen($aggregatedMessage),
                'has_media' => $mediaContext !== null,
            ],
        );

        // Campaign detection runs against the freshly persisted lead — keeping
        // it inside the persister keeps "what the CRM knows about this contact"
        // in one place. Failure of the detector must not hide the message.
        $campaign = null;
        try {
            $campaign = $this->campaignDetector->detect($lead, $phone, $tenantId);
            $lead->refresh();
        } catch (\Throwable $e) {
            Log::warning('whatsapp_persister.campaign_detect_failed', [
                'interaction_id' => $interactionId,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }

        // ATOM-4: the followup deactivation and the inbound bookkeeping are one logical
        // state change — apply them in a single transaction so a crash between the two
        // writes can't leave the lead with followups disabled but never marked inbound
        // (a "dark" lead: no follow-up fires and the AI never re-engages). resolveMode is
        // a pure read (lead mode / instance default, not followup_status), so computing it
        // up front is order-independent. timeline->record stays the final write below, so a
        // failure before it triggers a clean full retry rather than committing a half-inbound.
        $mode = $this->automation->resolveMode($lead, $instanceName);

        // Campaign attribution (Slice 5): when this inbound replies to a campaign, open the
        // atendimento as a campaign cycle and stamp campaign_id so conversion is attributed to
        // this session, not the lead's whole life. A post-terminal reengagement still wins the
        // open_reason (the guard below depends on it) — the campaign_id metadata is kept regardless.
        $sessionReason = null;
        $sessionMetadata = [];
        if ($campaign !== null) {
            $sessionMetadata['campaign_id'] = $campaign->id;

            if (! in_array((string) $lead->status, ConversationSessionLifecycleService::TERMINAL_STATUSES, true)) {
                $sessionReason = ConversationSession::OPEN_REASON_CAMPAIGN;
            }
        }

        // Open (or reuse) the atendimento this inbound belongs to before the inbound
        // bookkeeping, so markInbound and the timeline row below both see the session.
        $session = $this->sessions->ensureOpenSession($lead, $sessionReason, $sessionMetadata);

        DB::transaction(function () use ($lead, $mode, $referral): void {
            if ($lead->followup_status === 'active') {
                $lead->update(['followup_status' => 'inactive']);
            }

            $this->automation->markInbound($lead, $mode, $referral);
        });

        // A returning inbound after a terminal status opens a fresh session: the AI must
        // not answer a concluded sale — park it in the human queue and pause automation.
        // markInbound leaves the terminal stage frozen, so this override wins cleanly.
        if ($session->wasRecentlyCreated
            && $session->open_reason === ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL) {
            $this->sessions->applyPostTerminalGuard($lead);
            $lead->refresh();
        }

        try {
            $timelineMessage = $this->timeline->record(
                lead: $lead,
                direction: 'inbound',
                senderType: 'lead',
                body: $aggregatedMessage,
                media: $mediaContext,
                status: 'received',
                source: 'webhook',
                interactionId: $interactionId,
                providerMessageId: $providerMessageId,
                sessionId: $session->id,
            );
        } catch (QueryException $e) {
            // A concurrent redelivery won the insert race because the Redis lock degraded;
            // the ctm_tenant_provider_inbound_unique partial index rejected this duplicate.
            // Resolve to the row the winner persisted and report a duplicate rather than
            // failing the job or double-inserting (ATOM-2).
            $existingMessage = $providerMessageId !== null && $this->isUniqueViolation($e)
                ? ConversationTimelineMessage::query()
                    ->where('tenant_id', (string) $tenantId)
                    ->where('provider_message_id', $providerMessageId)
                    ->where('direction', 'inbound')
                    ->first()
                : null;

            if (! $existingMessage) {
                throw $e;
            }

            Log::warning('whatsapp_persister.duplicate_insert_race', [
                'interaction_id' => $interactionId,
                'provider_message_id' => $providerMessageId,
                'lead_id' => $lead->id,
            ]);

            return [
                'lead' => $lead,
                'timelineMessage' => $existingMessage,
                'mode' => $mode,
                'duplicate' => true,
            ];
        }

        // Backfill the campaign templates that reached this phone before the lead existed, so a
        // first-reply conversation opens already showing the message that started it. New leads
        // only; ordered by original sent_at ahead of this inbound. Best-effort — never throws.
        if ($leadWasCreated) {
            $this->campaignTimeline->backfillForNewLead($lead);
        }

        $this->events->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'conversation_persisted',
            eventSource: 'incoming_conversation_persister',
            payload: [
                'timeline_message_id' => $timelineMessage->id,
                'mode' => $mode,
                'provider_message_id' => $providerMessageId,
            ],
        );

        return [
            'lead' => $lead,
            'timelineMessage' => $timelineMessage,
            'mode' => $mode,
            'duplicate' => false,
        ];
    }

    /**
     * Unique-constraint violation across drivers: SQLSTATE 23505 (Postgres) or 23000
     * (SQLite/MySQL integrity constraint). Used to distinguish the inbound dedup race
     * from any other query failure, which must still propagate.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23505', '23000'], true);
    }
}
