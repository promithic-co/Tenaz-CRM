<?php

namespace App\Jobs;

use App\Models\WhatsappInstance;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Downloads incoming WhatsApp media outside of the webhook HTTP request.
 *
 * Why: Meta retries the webhook if we don't respond within ~15s. Synchronously fetching
 * the media URL + binary (up to 60s combined timeout) inside the controller risks
 * duplicate deliveries on slow Graph API responses. This job lets the webhook return
 * 200 immediately and resolves the media on a background worker.
 */
class DownloadIncomingMediaJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    /**
     * @param  array<string, mixed>  $messageData  The original Meta `messages[0]` payload.
     * @param  array<string, mixed>  $webhookPayload  Full webhook body — replayed to provider.
     */
    public function __construct(
        public readonly int $instanceId,
        public readonly string $phone,
        public readonly string $pushName,
        public readonly string $tenantId,
        public readonly ?int $agentId,
        public readonly string $instanceName,
        public readonly array $messageData,
        public readonly array $webhookPayload,
        public readonly string $interactionId,
        public readonly ?string $providerMessageId,
        public readonly ?array $referral = null,
    ) {
        $this->onQueue('media');
    }

    public function handle(WhatsAppProviderFactory $factory): void
    {
        $instance = WhatsappInstance::withoutGlobalScope('tenant')->find($this->instanceId);

        if (! $instance) {
            Log::warning('download_media_job.instance_not_found', [
                'interaction_id' => $this->interactionId,
                'instance_id' => $this->instanceId,
            ]);

            return;
        }

        $provider = $factory->makeProvider($instance);

        // Replay the original webhook payload through a synthetic Request so the provider's
        // downloadMedia signature (which historically accepted the live Request) remains
        // backward compatible without forcing a wider refactor.
        $request = new Request($this->webhookPayload);
        $mediaContext = $provider->downloadMedia($request, $this->messageData);

        if ($mediaContext === null) {
            Log::warning('download_media_job.media_unavailable', [
                'interaction_id' => $this->interactionId,
                'instance_id' => $this->instanceId,
                'phone' => $this->phone,
                'provider_message_id' => $this->providerMessageId,
            ]);

            // Forward without media so the conversation still progresses — better than
            // dropping the message entirely.
            ProcessIncomingWhatsAppMessageJob::dispatch(
                $this->phone,
                $this->pushName,
                $this->tenantId,
                $this->agentId,
                $this->instanceName,
                (string) ($this->messageData[$this->messageData['type'] ?? 'text']['caption'] ?? ''),
                null,
                $this->interactionId,
                $this->providerMessageId,
                null,
                $this->referral,
            );

            return;
        }

        ProcessIncomingWhatsAppMessageJob::dispatch(
            $this->phone,
            $this->pushName,
            $this->tenantId,
            $this->agentId,
            $this->instanceName,
            $mediaContext->caption ?? '',
            $mediaContext->toArray(),
            $this->interactionId,
            $this->providerMessageId,
            null,
            $this->referral,
        );
    }
}
