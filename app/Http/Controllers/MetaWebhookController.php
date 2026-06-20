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

    public function handle(Request $request): Response
    {
        if (! $this->verifyGlobalSignature($request)) {
            Log::warning('meta.webhook_unauthorized', [
                'ip' => $request->ip(),
            ]);

            return response()->noContent(401);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (is_array($change)) {
                    $this->handleChange($request, $entry, $change);
                }
            }
        }

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $change
     */
    private function handleChange(Request $request, array $entry, array $change): void
    {
        $value = $change['value'] ?? [];
        if (! is_array($value)) {
            return;
        }

        $changeField = (string) ($change['field'] ?? '');
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
        $wabaId = $entry['id'] ?? null;
        $instance = $this->resolveInstance($phoneNumberId, $wabaId);

        if (! $instance) {
            Log::info('meta.webhook_unknown_instance', [
                'phone_number_id' => $phoneNumberId,
                'waba_id' => $wabaId,
                'field' => $changeField,
            ]);

            return;
        }

        if (in_array($changeField, ['message_template_status_update', 'phone_number_quality_update', 'template_category_update'], true)) {
            $this->templateStatus->handleValue($instance, $changeField, $value);

            return;
        }

        $statuses = $value['statuses'] ?? [];
        if (is_array($statuses) && $statuses !== []) {
            $this->handleStatuses($statuses, $instance);
        }

        $messages = $value['messages'] ?? [];
        if (! is_array($messages) || $messages === []) {
            return;
        }

        $contacts = $value['contacts'] ?? [];
        $provider = $this->factory->makeProvider($instance, allowExpiredToken: true);

        foreach ($messages as $messageData) {
            if (! is_array($messageData)) {
                continue;
            }

            $dto = $provider->parseMessage($messageData, is_array($contacts) ? $contacts : [], $request->all());
            if ($dto) {
                $this->handleIncomingMessage($request, $instance, $dto, $messageData);
            }
        }
    }

    private function resolveInstance(mixed $phoneNumberId, mixed $wabaId): ?WhatsappInstance
    {
        if ($phoneNumberId) {
            return WhatsappInstance::withoutGlobalScope('tenant')
                ->where('meta_phone_number_id', (string) $phoneNumberId)
                ->first();
        }

        if ($wabaId) {
            return WhatsappInstance::withoutGlobalScope('tenant')
                ->where('meta_waba_id', (string) $wabaId)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $messageData
     */
    private function handleIncomingMessage(Request $request, WhatsappInstance $instance, IncomingMessageDTO $dto, array $messageData): void
    {
        if ($dto->messageId !== null && ! Cache::add("wamid:{$dto->messageId}", 1, now()->addDay())) {
            Log::info('meta.webhook_replay_skipped', [
                'provider_message_id' => $dto->messageId,
                'instance' => $instance->name,
            ]);

            return;
        }

        $context = $this->agentContext->resolveFromInstanceName($instance->name);
        $tenantId = $context['tenant_id'];
        $agentId = $context['agent_id'];
        $interactionId = $this->interactionEvents->newInteractionId();

        if ($dto->hasMedia) {
            $this->handleMedia($request, $instance, $dto, (string) $tenantId, $agentId, $interactionId, $messageData);

            return;
        }

        $this->handleText($instance, $dto, $tenantId, $agentId, $interactionId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $statuses
     */
    private function handleStatuses(array $statuses, WhatsappInstance $instance): void
    {
        foreach ($statuses as $status) {
            $wamid = $status['id'] ?? null;
            $eventType = $status['status'] ?? null;
            $errors = $status['errors'] ?? [];
            $opaqueId = $status['biz_opaque_callback_data'] ?? null;

            if (! $wamid || ! $eventType) {
                continue;
            }

            // Defense-in-depth dedupe: Meta retries and can deliver duplicate status
            // events. Drop an obvious replay before it reaches the queue. The job's
            // row-locked transition is the authoritative idempotency guarantee.
            if (! Cache::add("wamid_status:{$wamid}:{$eventType}", 1, now()->addDay())) {
                Log::info('meta.webhook_status_replay_skipped', [
                    'provider_message_id' => $wamid,
                    'event_type' => $eventType,
                ]);

                continue;
            }

            ProcessCampaignDeliveryEventJob::dispatch(
                (string) $wamid,
                (string) $eventType,
                is_array($errors) ? $errors : [],
                is_scalar($opaqueId) ? (string) $opaqueId : null,
                $instance->id,
                (string) $instance->tenant_id,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $messageData
     */
    private function handleMedia(
        Request $request,
        WhatsappInstance $instance,
        IncomingMessageDTO $dto,
        string $tenantId,
        ?int $agentId,
        string $interactionId,
        array $messageData,
    ): void {
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
    }

    private function handleText(
        WhatsappInstance $instance,
        IncomingMessageDTO $dto,
        mixed $tenantId,
        ?int $agentId,
        string $interactionId,
    ): void {
        $message = $dto->text ?? '';

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

            return;
        }

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
