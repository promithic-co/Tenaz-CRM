<?php

namespace App\Actions;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\AgentInteractionEventService;
use App\Services\ConversationAutomationService;
use App\Services\ConversationTimelineService;
use App\Services\ServiceTicketLifecycleService;
use App\Services\WhatsApp\WhatsAppConversationWindowResolver;
use App\Services\WhatsappOutboxService;
use Illuminate\Http\UploadedFile;

class SendOperatorMessageAction
{
    public function __construct(
        private readonly ConversationTimelineService $timeline,
        private readonly WhatsappOutboxService $outbox,
        private readonly ConversationAutomationService $automation,
        private readonly AgentInteractionEventService $interactionEvents,
        private readonly ServiceTicketLifecycleService $ticketLifecycle,
        private readonly StoreInboundMediaAction $storeMedia,
        private readonly WhatsAppConversationWindowResolver $windowResolver,
    ) {}

    /**
     * Orchestrate an operator-initiated outbound message (text and/or file) to a
     * lead's WhatsApp. Verbatim behaviour port of
     * ConversasController::sendMessage.
     *
     * Returns null when the lead has no resolvable WhatsApp instance — the
     * caller maps that to the 422 contract. The instance is resolved explicitly
     * with NO global-default fallback so a message is never routed through the
     * wrong provider/tenant in a multi-instance setup.
     *
     * @return array{message: array<string, mixed>, outbox_id: int|null}|null
     */
    public function send(
        Lead $lead,
        ?string $content,
        ?UploadedFile $file,
        User $actor,
        bool $broadcastToOthers,
    ): ?array {
        $interactionId = $this->interactionEvents->newInteractionId();

        // Resolve the lead's WhatsApp instance explicitly — never fall back to a global
        // default config, because that can route the message through the wrong provider
        // (or wrong tenant entirely) in a multi-instance / multi-provider setup.
        $instanceModel = $lead->whatsapp_instance_id
            ? WhatsappInstance::query()
                ->where('tenant_id', $lead->tenant_id)
                ->whereKey($lead->whatsapp_instance_id)
                ->first()
            : null;

        if (! $instanceModel) {
            return null;
        }

        $instance = $instanceModel->name;
        $providerKey = $instanceModel->provider?->value ?? 'meta_cloud';

        // Server-side 24h window guard (defense against a stale UI or a direct API call).
        // Throws ValidationException → 422 when the window is closed. Template sends do not
        // reach this action, so they are unaffected.
        $this->windowResolver->ensureFreeFormAllowed($lead, $providerKey);

        $mediaData = null;
        $timelineMessage = null;

        if ($file !== null) {
            $stored = $this->storeMedia->store($file, $content);
            $mediaData = $stored['mediaData'];

            $timelineMessage = $this->timeline->record(
                lead: $lead,
                direction: 'outbound',
                senderType: 'human',
                body: $content ?? '',
                media: $mediaData,
                status: 'queued',
                source: 'manual',
                interactionId: $interactionId,
            );

            // Pass a disk reference instead of inline base64. The outbox worker will
            // load+encode at send-time, keeping HTTP request memory bounded.
            $outboxMessage = $this->outbox->queue(
                tenantId: $lead->tenant_id,
                payload: [
                    'type' => 'media',
                    'instance_id' => $instanceModel->id,
                    'instance_name' => $instance,
                    'phone' => $lead->whatsapp,
                    'disk' => 'local',
                    'disk_path' => $stored['diskPath'],
                    'mime_type' => $stored['mimeType'],
                    'media_type' => $stored['mediaType'],
                    'file_name' => $stored['fileName'],
                    'caption' => $content,
                ],
                provider: $providerKey,
                idempotencyKey: "manual:{$lead->id}:{$interactionId}:media",
                lead: $lead,
                timelineMessage: $timelineMessage,
                interactionId: $interactionId,
                sourceType: 'lead',
                sourceId: $lead->id,
            );
        } else {
            $outboxMessage = $this->outbox->queueTextForLead(
                lead: $lead,
                instance: $instanceModel,
                phone: $lead->whatsapp,
                text: (string) $content,
                source: 'manual',
                senderType: 'human',
                interactionId: $interactionId,
                idempotencyKey: "manual:{$lead->id}:{$interactionId}:text",
            );
            $timelineMessage = $outboxMessage->timelineMessage;
        }

        $this->interactionEvents->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'outbound_queued',
            eventSource: 'conversas_controller_manual_send',
            payload: [
                'source' => 'manual',
                'sender_type' => 'human',
                'user_id' => $actor->id,
                'instance_name' => $instance,
                'has_media' => $mediaData !== null,
                'message_length' => strlen($content ?? ''),
            ],
        );

        $timelineMessage = $timelineMessage->fresh();
        $this->timeline->broadcast($timelineMessage, $broadcastToOthers);
        $this->automation->pauseForHuman($lead, $actor, 'manual_message');
        $this->ticketLifecycle->markHumanResponse($lead, $actor);

        return [
            'message' => $this->timeline->toFrontendMessage($timelineMessage),
            'outbox_id' => $outboxMessage?->id,
        ];
    }
}
