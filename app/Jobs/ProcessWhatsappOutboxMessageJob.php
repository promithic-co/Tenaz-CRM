<?php

namespace App\Jobs;

use App\Models\CampaignMessage;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Services\AgentInteractionEventService;
use App\Services\ConversationTimelineService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessWhatsappOutboxMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public readonly int $outboxId)
    {
        $this->onQueue('outbox');
    }

    public function handle(WhatsAppService $whatsapp, ConversationTimelineService $timeline): void
    {
        $outbox = WhatsappOutboxMessage::query()->find($this->outboxId);

        if (! $outbox || $outbox->status === 'sent') {
            return;
        }

        if ($outbox->scheduled_at && $outbox->scheduled_at->isFuture()) {
            $this->release(now()->diffInSeconds($outbox->scheduled_at));

            return;
        }

        $outbox->markSending();

        if ($outbox->timelineMessage) {
            $outbox->timelineMessage->update(['status' => 'sending']);
            $timeline->broadcast($outbox->timelineMessage->fresh());
        }

        try {
            $payload = $outbox->payload_json;
            $instance = $this->resolveInstance($payload, (string) $outbox->tenant_id);
            $providerMessageId = match ($payload['type'] ?? 'text') {
                'media' => $whatsapp->sendMediaViaInstance(
                    instance: $instance,
                    phone: (string) $payload['phone'],
                    mediaContent: $this->resolveMediaBase64($payload),
                    mimeType: (string) $payload['mime_type'],
                    mediaType: (string) $payload['media_type'],
                    fileName: $payload['file_name'] ?? null,
                    caption: $payload['caption'] ?? null,
                ),
                default => $whatsapp->sendTextViaInstance(
                    instance: $instance,
                    phone: (string) $payload['phone'],
                    text: (string) $payload['text'],
                ),
            };

            if ($providerMessageId === null || $providerMessageId === '') {
                throw new \RuntimeException("WhatsApp provider did not confirm delivery for instance {$instance->name}.");
            }

            $outbox->markSent($providerMessageId);
            $this->syncSourceModel($outbox->fresh(), $providerMessageId);

            if ($outbox->timelineMessage) {
                $outbox->timelineMessage->update([
                    'status' => 'sent',
                    'provider_message_id' => $providerMessageId,
                ]);
                $timeline->broadcast($outbox->timelineMessage->fresh());
            }

            if ($outbox->lead) {
                app(AgentInteractionEventService::class)->recordForLead(
                    interactionId: $outbox->interaction_id ?? app(AgentInteractionEventService::class)->newInteractionId(),
                    lead: $outbox->lead,
                    eventType: 'outbound_sent',
                    eventSource: 'process_whatsapp_outbox_message_job',
                    payload: [
                        'outbox_id' => $outbox->id,
                        'provider_message_id' => $providerMessageId,
                    ],
                );
            }
        } catch (Throwable $e) {
            $outbox->markFailed($e->getMessage());

            if ($outbox->timelineMessage) {
                $outbox->timelineMessage->update(['status' => 'failed']);
                $timeline->broadcast($outbox->timelineMessage->fresh());
            }

            if ($outbox->lead) {
                app(AgentInteractionEventService::class)->recordForLead(
                    interactionId: $outbox->interaction_id ?? app(AgentInteractionEventService::class)->newInteractionId(),
                    lead: $outbox->lead,
                    eventType: 'outbound_failed',
                    eventSource: 'process_whatsapp_outbox_message_job',
                    payload: [
                        'outbox_id' => $outbox->id,
                        'error' => $e->getMessage(),
                    ],
                    severity: 'error',
                );
            }

            Log::error('whatsapp_outbox.failed', [
                'outbox_id' => $outbox->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveInstance(array $payload, string $tenantId): WhatsappInstance
    {
        $instanceId = $payload['instance_id'] ?? null;
        if ($instanceId === null) {
            throw new \RuntimeException('WhatsApp outbox payload missing instance_id.');
        }

        $instance = WhatsappInstance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey((int) $instanceId)
            ->first();

        if ($instance === null) {
            throw new \RuntimeException('WhatsApp outbox payload instance_id does not belong to this tenant.');
        }

        return $instance;
    }

    private function syncSourceModel(WhatsappOutboxMessage $outbox, ?string $providerMessageId): void
    {
        if ($outbox->source_type === 'campaign_message' && $outbox->source_id) {
            CampaignMessage::query()->find($outbox->source_id)?->markSent((string) $providerMessageId);
        }
    }

    /**
     * Resolve a media payload's base64 content. Newer enqueues store only a `disk_path`
     * reference so the HTTP request that originated the upload doesn't have to hold the
     * encoded blob in memory. Older payloads (backward compat) may still carry `base64`
     * inline, in which case we use it directly.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveMediaBase64(array $payload): string
    {
        if (! empty($payload['base64'])) {
            return (string) $payload['base64'];
        }

        $disk = (string) ($payload['disk'] ?? 'local');
        $diskPath = (string) ($payload['disk_path'] ?? '');

        if ($diskPath === '') {
            throw new \RuntimeException('Media outbox payload missing both base64 and disk_path.');
        }

        $contents = Storage::disk($disk)->get($diskPath);

        if ($contents === null) {
            throw new \RuntimeException("Media file not found on disk '{$disk}': {$diskPath}");
        }

        return base64_encode($contents);
    }
}
