<?php

namespace App\Http\Controllers;

use App\DTOs\WhatsApp\IncomingMessageDTO;
use App\Jobs\AggregateDebouncedMessageJob;
use App\Jobs\DownloadIncomingMediaJob;
use App\Jobs\ProcessCampaignDeliveryEventJob;
use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Models\WhatsappInstance;
use App\Services\AgentContextResolver;
use App\Services\AgentInteractionEventService;
use App\Services\DebounceService;
use App\Services\TemplateStatusUpdateService;
use App\Services\WhatsApp\Providers\MetaCloudProvider;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppProviderFactory $factory,
        private readonly AgentContextResolver $agentContext,
        private readonly DebounceService $debounce,
        private readonly AgentInteractionEventService $interactionEvents,
        private readonly TemplateStatusUpdateService $templateStatus,
    ) {}

    /**
     * GET /webhooks/meta — subscription challenge handshake.
     * Meta sends hub.mode=subscribe&hub.verify_token={token}&hub.challenge={string}.
     * PHP converts dots to underscores in query keys.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $verifyToken = $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge', '');

        $expected = (string) config('services.meta.verify_token', '');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $verifyToken)) {
            return response($challenge, 200);
        }

        Log::warning('meta.webhook_verify_failed', [
            'mode' => $mode,
            'token_matches' => $verifyToken === $expected,
            'ip' => $request->ip(),
        ]);

        return response('', 403);
    }

    /**
     * POST /webhooks/meta — verify the signature first (security boundary),
     * resolve the instance, then route to the correct typed handler.
     */
    public function handle(Request $request): Response
    {
        if (! $this->verifyGlobalSignature($request)) {
            Log::warning('meta.webhook_unauthorized', [
                'ip' => $request->ip(),
            ]);

            return response()->noContent(401);
        }

        $phoneNumberId = $request->input('entry.0.changes.0.value.metadata.phone_number_id');
        $changeField = $request->input('entry.0.changes.0.field');
        $wabaId = $request->input('entry.0.id');

        $instance = null;
        if ($phoneNumberId) {
            $instance = WhatsappInstance::withoutGlobalScope('tenant')
                ->where('meta_phone_number_id', (string) $phoneNumberId)
                ->first();
        } elseif ($wabaId) {
            $instance = WhatsappInstance::withoutGlobalScope('tenant')
                ->where('meta_waba_id', (string) $wabaId)
                ->first();
        }

        if (! $instance) {
            Log::info('meta.webhook_unknown_instance', [
                'phone_number_id' => $phoneNumberId,
                'waba_id' => $wabaId,
                'field' => $changeField,
            ]);

            return response()->noContent();
        }

        $provider = $this->factory->makeProvider($instance);

        if (in_array($changeField, ['message_template_status_update', 'phone_number_quality_update', 'template_category_update'], true)) {
            $this->templateStatus->handle($request, $instance, $changeField);

            return response()->noContent();
        }

        $statuses = $request->input('entry.0.changes.0.value.statuses', []);
        if (is_array($statuses) && $statuses !== []) {
            return $this->handleStatuses($statuses);
        }

        $dto = $provider->parseWebhook($request);
        if (! $dto) {
            return response()->noContent();
        }

        // Replay guard (F10): Meta re-delivers the same wamid when our ACK misses its
        // ~15s retry window. SETNX after signature verification — first delivery wins,
        // duplicates are ACKed without re-dispatching. Covers both text and media paths.
        if ($dto->messageId !== null && ! Cache::add("wamid:{$dto->messageId}", 1, now()->addDay())) {
            Log::info('meta.webhook_replay_skipped', [
                'provider_message_id' => $dto->messageId,
                'instance' => $instance->name,
            ]);

            return response()->noContent();
        }

        $context = $this->agentContext->resolveFromInstanceName($instance->name);
        $tenantId = $context['tenant_id'];
        $agentId = $context['agent_id'];
        $interactionId = $this->interactionEvents->newInteractionId();

        // Defer media download to a background job so the webhook returns inside Meta's
        // ~15s retry window even when the Graph API media URL is slow. Text-only messages
        // dispatch the processing job directly as before.
        if ($dto->hasMedia) {
            return $this->handleMedia($request, $instance, $dto, (string) $tenantId, $agentId, $interactionId);
        }

        return $this->handleText($instance, $dto, $tenantId, $agentId, $interactionId);
    }

    /**
     * Delivery status callbacks → fan out one ProcessCampaignDeliveryEventJob
     * per status entry.
     *
     * @param  array<int, array<string, mixed>>  $statuses
     */
    private function handleStatuses(array $statuses): Response
    {
        foreach ($statuses as $status) {
            $wamid = $status['id'] ?? null;
            $eventType = $status['status'] ?? null;
            $errors = $status['errors'] ?? [];

            if ($wamid && $eventType) {
                ProcessCampaignDeliveryEventJob::dispatch((string) $wamid, (string) $eventType, is_array($errors) ? $errors : []);
            }
        }

        return response()->noContent();
    }

    /**
     * Inbound media message → record the interaction and queue the deferred
     * Graph API download so the HTTP worker returns inside Meta's retry window.
     */
    private function handleMedia(
        Request $request,
        WhatsappInstance $instance,
        IncomingMessageDTO $dto,
        string $tenantId,
        ?int $agentId,
        string $interactionId,
    ): Response {
        $messageData = $request->input('entry.0.changes.0.value.messages.0', []);

        Log::info('meta.incoming_media_queued', [
            'interaction_id' => $interactionId,
            'phone' => $dto->phone,
            'instance' => $instance->name,
            'provider_message_id' => $dto->messageId,
        ]);

        $this->interactionEvents->record(
            interactionId: $interactionId,
            tenantId: $tenantId,
            eventType: 'webhook_received',
            eventSource: 'meta_webhook_controller',
            payload: [
                'channel' => 'whatsapp',
                'provider' => $instance->provider->value,
                'instance_name' => $instance->name,
                'provider_message_id' => $dto->messageId,
                'phone' => $dto->phone,
                'has_media' => true,
                'media_pending_download' => true,
            ],
            agentId: $agentId,
        );

        DownloadIncomingMediaJob::dispatch(
            $instance->id,
            $dto->phone,
            $dto->pushName ?? '',
            $tenantId,
            $agentId,
            $instance->name,
            $messageData,
            $request->all(),
            $interactionId,
            $dto->messageId,
            $dto->referral,
        );

        return response()->noContent();
    }

    /**
     * Inbound text message → buffer non-greetings through the debounce window
     * (drained by a delayed job), or process short greetings immediately.
     */
    private function handleText(
        WhatsappInstance $instance,
        IncomingMessageDTO $dto,
        mixed $tenantId,
        ?int $agentId,
        string $interactionId,
    ): Response {
        $message = $dto->text ?? '';

        // Mensagens não-saudação passam pelo buffer de debounce, drenado por um
        // job atrasado — o worker HTTP nunca bloqueia esperando a janela.
        if (! $this->debounce->isQuickCommand($message)) {
            if ($this->debounce->push($dto->phone, $message)) {
                AggregateDebouncedMessageJob::dispatch(
                    $dto->phone,
                    $dto->pushName ?? '',
                    (string) $tenantId,
                    $agentId,
                    $instance->name,
                    $instance->provider->value,
                    $interactionId,
                    $dto->messageId,
                    $dto->referral,
                )->delay(now()->addSeconds((int) config('credflow.debounce_seconds', 3)));
            }

            return response()->noContent();
        }

        // Saudações curtas são processadas imediatamente.
        Log::info('meta.incoming', [
            'interaction_id' => $interactionId,
            'phone' => $dto->phone,
            'instance' => $instance->name,
            'msg_len' => strlen($message),
            'has_media' => false,
        ]);

        $this->interactionEvents->record(
            interactionId: $interactionId,
            tenantId: $tenantId,
            eventType: 'webhook_received',
            eventSource: 'meta_webhook_controller',
            payload: [
                'channel' => 'whatsapp',
                'provider' => $instance->provider->value,
                'instance_name' => $instance->name,
                'provider_message_id' => $dto->messageId,
                'phone' => $dto->phone,
                'has_media' => false,
                'message_length' => strlen($message),
            ],
            agentId: $agentId,
        );

        ProcessIncomingWhatsAppMessageJob::dispatch(
            $dto->phone,
            $dto->pushName ?? '',
            $tenantId,
            $agentId,
            $instance->name,
            $message,
            null,
            $interactionId,
            $dto->messageId,
            null,
            $dto->referral,
        );

        return response()->noContent();
    }

    private function verifyGlobalSignature(Request $request): bool
    {
        $secret = (string) config('services.meta.app_secret', '');

        if ($secret === '') {
            if (app()->environment('local', 'testing')) {
                Log::warning('meta.webhook_signature_skipped_local', [
                    'env' => app()->environment(),
                    'ip' => $request->ip(),
                ]);

                return true;
            }

            Log::error('meta.webhook_signature_misconfigured', [
                'env' => app()->environment(),
                'ip' => $request->ip(),
            ]);

            return false;
        }

        return MetaCloudProvider::isValidSignature(
            $request->getContent(),
            (string) $request->header('X-Hub-Signature-256', ''),
            $secret,
        );
    }
}
