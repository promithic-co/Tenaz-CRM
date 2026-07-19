<?php

namespace App\Actions;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\AgentInteractionEventService;
use App\Services\ConversationAutomationService;
use App\Services\ConversationTimelineService;
use App\Services\ServiceTicketLifecycleService;
use App\Services\WhatsApp\WhatsappTemplateRenderer;
use App\Services\WhatsappOutboxService;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Sends an approved WhatsApp template from inside a conversation. Unlike a free-form message,
 * a template is always allowed — it is the sanctioned way to (re)open a closed 24h window — so
 * this action deliberately does NOT run the window guard.
 *
 * Mirrors the durable path of the text send: it records a timeline row (with a full immutable
 * template snapshot in metadata) and queues the actual provider call through the outbox, rather
 * than sending synchronously in-request.
 */
class SendOperatorTemplateAction
{
    public function __construct(
        private readonly ConversationTimelineService $timeline,
        private readonly WhatsappOutboxService $outbox,
        private readonly ConversationAutomationService $automation,
        private readonly AgentInteractionEventService $interactionEvents,
        private readonly ServiceTicketLifecycleService $ticketLifecycle,
        private readonly WhatsappTemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters  shaped as ['header'=>[...], 'body'=>[...], 'buttons'=>[...]]
     * @return array{message: array<string, mixed>, outbox_id: int|null}|null null when the lead has no instance
     *
     * @throws ValidationException on any template validation failure (maps to 422)
     */
    public function send(
        Lead $lead,
        int $templateId,
        array $parameters,
        User $actor,
        bool $broadcastToOthers,
    ): ?array {
        $instanceModel = $lead->whatsapp_instance_id
            ? WhatsappInstance::query()
                ->where('tenant_id', $lead->tenant_id)
                ->whereKey($lead->whatsapp_instance_id)
                ->first()
            : null;

        if (! $instanceModel) {
            return null;
        }

        $template = WhatsappTemplate::query()
            ->where('tenant_id', $lead->tenant_id)
            ->whereKey($templateId)
            ->first();

        $this->assertSendable($template, $instanceModel);

        $components = is_array($template->components_json) ? $template->components_json : [];
        $sections = $this->normalizeParameters($parameters);

        try {
            $rendered = $this->renderer->render($components, $sections);
            $providerComponents = $this->renderer->payload($components, $sections, (string) $template->id);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['template_parameters' => $e->getMessage()]);
        }

        $interactionId = $this->interactionEvents->newInteractionId();

        $timelineMessage = $this->timeline->record(
            lead: $lead,
            direction: 'outbound',
            senderType: 'human',
            body: $rendered['text'],
            status: 'queued',
            source: 'manual_template',
            interactionId: $interactionId,
            metadata: [
                'whatsapp_template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'meta_template_name' => $template->meta_template_name,
                    'language' => $template->language,
                    'category' => $template->category,
                    'components' => $components,
                    'parameters' => $sections,
                    'rendered' => $rendered,
                ],
            ],
        );

        $outboxMessage = $this->outbox->queue(
            tenantId: $lead->tenant_id,
            payload: [
                'type' => 'template',
                'instance_id' => $instanceModel->id,
                'instance_name' => $instanceModel->name,
                'provider' => $instanceModel->provider?->value ?? 'meta_cloud',
                'phone' => $lead->whatsapp,
                'template_name' => (string) ($template->meta_template_name ?: $template->name),
                'lang_code' => (string) ($template->language ?: 'pt_BR'),
                'components' => $providerComponents,
            ],
            provider: $instanceModel->provider?->value ?? 'meta_cloud',
            idempotencyKey: "manual:{$lead->id}:{$interactionId}:template",
            lead: $lead,
            timelineMessage: $timelineMessage,
            interactionId: $interactionId,
            sourceType: 'lead',
            sourceId: $lead->id,
        );

        $this->interactionEvents->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'outbound_queued',
            eventSource: 'conversas_controller_manual_template',
            payload: [
                'source' => 'manual_template',
                'sender_type' => 'human',
                'user_id' => $actor->id,
                'template_id' => $template->id,
                'instance_name' => $instanceModel->name,
            ],
        );

        $timelineMessage = $timelineMessage->fresh();
        $this->timeline->broadcast($timelineMessage, $broadcastToOthers);
        $this->automation->pauseForHuman($lead, $actor, 'manual_template');
        $this->ticketLifecycle->markHumanResponse($lead, $actor);

        return [
            'message' => $this->timeline->toFrontendMessage($timelineMessage),
            'outbox_id' => $outboxMessage?->id,
        ];
    }

    /**
     * @throws ValidationException
     *
     * @phpstan-assert WhatsappTemplate $template
     */
    private function assertSendable(?WhatsappTemplate $template, WhatsappInstance $instance): void
    {
        if ($template === null) {
            throw ValidationException::withMessages(['template_id' => 'Template não encontrado.']);
        }

        if ($template->status !== 'APPROVED') {
            throw ValidationException::withMessages(['template_id' => 'Este template não está aprovado para envio.']);
        }

        $belongsToInstance = $template->whatsapp_instance_id === $instance->id
            || ($template->meta_waba_id !== null && $template->meta_waba_id === $instance->meta_waba_id);

        if (! $belongsToInstance) {
            throw ValidationException::withMessages(['template_id' => 'Este template não pertence à instância desta conversa.']);
        }

        $components = is_array($template->components_json) ? $template->components_json : [];
        $description = $this->renderer->describe($components);

        if (! $description['supported']) {
            throw ValidationException::withMessages([
                'template_id' => (string) ($description['unsupported_reason'] ?? 'Template não suportado para envio.'),
            ]);
        }
    }

    /**
     * Coerce the request parameters into the renderer's section shape with string keys.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, array<string, string>>
     */
    private function normalizeParameters(array $parameters): array
    {
        $sections = [];

        foreach (['header', 'body', 'buttons'] as $section) {
            $values = $parameters[$section] ?? null;

            if (! is_array($values)) {
                continue;
            }

            $normalized = [];
            foreach ($values as $key => $value) {
                if (is_scalar($value)) {
                    $normalized[(string) $key] = (string) $value;
                }
            }

            if ($normalized !== []) {
                $sections[$section] = $normalized;
            }
        }

        return $sections;
    }
}
