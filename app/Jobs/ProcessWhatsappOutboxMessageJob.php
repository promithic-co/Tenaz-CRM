<?php

namespace App\Jobs;

use App\Exceptions\MetaAmbiguousSendException;
use App\Models\CampaignMessage;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Services\AgentInteractionEventService;
use App\Services\ConversationTimelineService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
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

    public int $maxExceptions = 3;

    public function __construct(public readonly int $outboxId)
    {
        $this->onQueue('outbox');
    }

    public function retryUntil(): ?\DateTimeInterface
    {
        $windowSeconds = (int) config('credflow.jobs.outbox_retry_window_seconds', 21600);

        return $windowSeconds > 0 ? now()->addSeconds($windowSeconds) : null;
    }

    public function handle(WhatsAppService $whatsapp, ConversationTimelineService $timeline): void
    {
        $outbox = WhatsappOutboxMessage::query()->find($this->outboxId);

        if (! $outbox || in_array($outbox->status, ['sent', 'in_doubt'], true)) {
            return;
        }

        if ($outbox->scheduled_at && $outbox->scheduled_at->isFuture()) {
            $this->release(now()->diffInSeconds($outbox->scheduled_at));

            return;
        }

        // Defensive in-doubt guard: a prior attempt already reached the provider POST
        // stage but never confirmed a send. Re-POSTing risks a duplicate, so we mark the
        // row in_doubt and let a webhook (or reconciliation) resolve it instead.
        if ($outbox->provider_attempted_at !== null) {
            $this->finalizeInDoubt($outbox, 'Re-execution after an unconfirmed provider attempt; not re-sending.');

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
            $mediaBase64 = ($payload['type'] ?? 'text') === 'media' ? $this->resolveMediaBase64($payload) : null;

            // Pre-flight is complete. Stamp the attempt immediately before the HTTP POST so
            // that an ambiguous transport failure (timeout/reset/5xx) is never blindly retried.
            $outbox->markProviderAttempted();

            $providerMessageId = match ($payload['type'] ?? 'text') {
                'media' => $whatsapp->sendMediaViaInstance(
                    instance: $instance,
                    phone: (string) $payload['phone'],
                    mediaContent: (string) $mediaBase64,
                    mimeType: (string) $payload['mime_type'],
                    mediaType: (string) $payload['media_type'],
                    fileName: $payload['file_name'] ?? null,
                    caption: $payload['caption'] ?? null,
                    opaqueId: $outbox->idempotency_key,
                ),
                default => $whatsapp->sendTextViaInstance(
                    instance: $instance,
                    phone: (string) $payload['phone'],
                    text: (string) $payload['text'],
                    opaqueId: $outbox->idempotency_key,
                ),
            };

            if ($providerMessageId === null || $providerMessageId === '') {
                // A 2xx with no message id is undecidable — do not retry into a duplicate.
                throw new MetaAmbiguousSendException("WhatsApp provider returned no message id for instance {$instance->name}.");
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
        } catch (MetaAmbiguousSendException $e) {
            // The POST may have reached Meta. Do NOT rethrow (no retry, no duplicate).
            $this->finalizeInDoubt($outbox, $e->getMessage());

            return;
        } catch (Throwable $e) {
            // Connection-refused / DNS proves nothing reached Meta — clear the marker so
            // the retry re-sends. Other unknown errors keep it (possibly-sent → in_doubt).
            if ($e instanceof ConnectionException) {
                $outbox->clearProviderAttempt();
            }

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
     * Mark an outbox row as in-doubt: the provider POST may or may not have been
     * accepted. We stop retrying and leave the row for webhook/reconciliation. The
     * timeline message is NOT marked failed (it might have been delivered) — it stays
     * in 'sending' so the UI does not falsely report a failure.
     */
    private function finalizeInDoubt(WhatsappOutboxMessage $outbox, string $reason): void
    {
        $outbox->markInDoubt($reason);

        if ($outbox->lead) {
            app(AgentInteractionEventService::class)->recordForLead(
                interactionId: $outbox->interaction_id ?? app(AgentInteractionEventService::class)->newInteractionId(),
                lead: $outbox->lead,
                eventType: 'outbound_in_doubt',
                eventSource: 'process_whatsapp_outbox_message_job',
                payload: [
                    'outbox_id' => $outbox->id,
                    'reason' => $reason,
                ],
                severity: 'error',
            );
        }

        Log::warning('whatsapp_outbox.in_doubt', [
            'outbox_id' => $outbox->id,
            'reason' => $reason,
        ]);
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
