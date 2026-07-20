<?php

namespace App\Services;

use App\Http\Resources\AgentInteractionEventResource;
use App\Http\Resources\ConversationResource;
use App\Models\AgentInteractionEvent;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\StatusMachine;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\WhatsAppConversationWindowResolver;
use App\Services\WhatsApp\WhatsappTemplateRenderer;

class ConversationPanelPropsBuilder
{
    public function __construct(
        private readonly PauseService $pause,
        private readonly ConversationTimelineService $timeline,
        private readonly ConversationAutomationService $automation,
        private readonly HumanHandoffStateService $handoffState,
        private readonly WhatsAppConversationWindowResolver $conversationWindow,
        private readonly ConversationTransferTargetsBuilder $transferTargets,
        private readonly ContactCollectedInformationService $collectedInformation,
        private readonly WhatsappTemplateRenderer $templateRenderer,
        private readonly FollowUpStateSummarizer $followUpState,
    ) {}

    /**
     * @return array{
     *     lead: array<string, mixed>,
     *     mensagens: array<int, array<string, mixed>>,
     *     sessions: list<array<string, mixed>>,
     *     pausado: bool,
     *     followupStatus: string|null,
     *     followupState: array<string, mixed>,
     *     followupHistory: mixed,
     *     conversationWindow: array<string, mixed>,
     *     recentEvents: mixed,
     *     canStartCampaign: bool,
     *     active_handoff: array<string, mixed>|null,
     *     handoff_state: array<string, mixed>,
     *     handoff_actions: array<string, mixed>,
     *     transfer_targets: list<array{type: string, id: int, name: string}>
     * }
     */
    public function build(Lead $lead, User $actor): array
    {
        $messages = $this->timeline->forLead($lead);

        if ($messages === [] && $lead->conversation_id) {
            $messages = $this->timeline->legacyMessages($lead);
        }

        $lead->load([
            'contact',
            'whatsappInstance',
            'agent.config',
            'tags' => fn ($query) => $query->withPivot('source', 'ai_confidence', 'ai_evidence', 'ai_evaluated_at'),
        ]);

        $availableTransitions = StatusMachine::forTenant((string) $lead->tenant_id)
            ->getAvailableTransitions((string) $lead->status);
        $effectiveAiMode = $this->automation->resolveModesByInstanceId(collect([$lead]))[$lead->id];

        $contactInformation = $lead->contact === null
            ? []
            : $this->collectedInformation->items($lead->contact);

        $leadData = (new ConversationResource($lead, $availableTransitions, $effectiveAiMode, $contactInformation))
            ->resolve(request());

        $followupHistory = $lead->followupMessages()
            ->orderByDesc('sent_at')
            ->limit(10)
            ->get(['attempt', 'message_text', 'tone', 'sent_at']);

        $recentEvents = AgentInteractionEventResource::collection(
            AgentInteractionEvent::query()
                ->where('lead_id', $lead->id)
                ->whereIn('event_type', [
                    'ai_paused_manual',
                    'ai_resumed_manual',
                    'history_cleared_manual',
                    'lead_created_manual',
                    'lead_deleted_manual',
                    'lead_bulk_action',
                    'followup_skipped',
                ])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['event_type', 'created_at', 'severity', 'payload_json'])
        );

        $activeTicket = ServiceTicket::query()->activeEscalation($lead->id)->with('assignedUser')->latest()->first();
        $activeHandoff = $activeTicket ? $this->handoffState->activeHandoffPayload($lead) : null;

        $instance = $lead->whatsappInstance;
        $templatesEnabled = $instance !== null
            && ($instance->provider?->value ?? null) === 'meta_cloud'
            && ! empty($instance->meta_waba_id);
        $templates = $templatesEnabled ? $this->buildTemplates($lead, $instance) : [];

        return [
            'lead' => $leadData,
            'mensagens' => $messages,
            'sessions' => $this->buildSessions($lead),
            'pausado' => $this->pause->isPaused($lead->whatsapp, $lead->tenant_id),
            'followupStatus' => $lead->followup_status,
            'followupState' => $this->followUpState->forLead($lead),
            'followupHistory' => $followupHistory,
            'conversationWindow' => $this->conversationWindow->resolve($lead),
            'recentEvents' => $recentEvents,
            'canStartCampaign' => $actor->isOwnerOrAdmin(),
            'active_handoff' => $activeHandoff,
            'handoff_state' => $this->handoffState->deriveState($lead, $activeTicket),
            'handoff_actions' => $this->handoffState->handoffActions($activeTicket),
            'transfer_targets' => $this->transferTargets->forTenant((string) $lead->tenant_id, $actor),
            'whatsappTemplatesEnabled' => $templatesEnabled,
            'whatsappTemplates' => $templates,
            'templateSync' => [
                'count' => count($templates),
                'synced_at' => $templates === [] ? null : ($templates[0]['last_synced_at'] ?? null),
            ],
        ];
    }

    /**
     * The lead's atendimentos (service cycles), newest first, so the panel can render
     * session dividers, the returning badge, and the close/new-atendimento controls.
     *
     * @return list<array{id: int, number: int, status: string, open_reason: string, outcome: string|null, opened_at: string|null, closed_at: string|null, last_message_at: string|null, is_returning: bool}>
     */
    private function buildSessions(Lead $lead): array
    {
        return $lead->sessions()
            ->orderByDesc('number')
            ->get()
            ->map(fn (ConversationSession $session): array => [
                'id' => $session->id,
                'number' => $session->number,
                'status' => $session->status,
                'open_reason' => $session->open_reason,
                'outcome' => $session->outcome,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'closed_at' => $session->closed_at?->toIso8601String(),
                'last_message_at' => $session->last_message_at?->toIso8601String(),
                'is_returning' => $session->isReengagement(),
            ])
            ->all();
    }

    /**
     * Approved, sendable templates for the lead's Meta Cloud instance, each with the dynamic
     * field manifest and a live preview so the frontend can render the parameter form.
     *
     * @return list<array<string, mixed>>
     */
    private function buildTemplates(Lead $lead, WhatsappInstance $instance): array
    {
        $templates = WhatsappTemplate::query()
            ->where('tenant_id', $lead->tenant_id)
            ->where('status', 'APPROVED')
            ->where(function ($query) use ($instance): void {
                $query->where('whatsapp_instance_id', $instance->id);

                if (! empty($instance->meta_waba_id)) {
                    $query->orWhere('meta_waba_id', $instance->meta_waba_id);
                }
            })
            ->orderByDesc('last_synced_at')
            ->orderBy('name')
            ->get();

        return $templates
            ->map(function (WhatsappTemplate $template): ?array {
                $components = is_array($template->components_json) ? $template->components_json : [];
                $description = $this->templateRenderer->describe($components);

                if (! $description['supported']) {
                    return null;
                }

                try {
                    $preview = $this->templateRenderer->preview($components)['text'];
                } catch (\Throwable) {
                    $preview = '';
                }

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'language' => $template->language,
                    'category' => $template->category,
                    'fields' => $description['fields'],
                    'preview' => $preview,
                    'last_synced_at' => $template->last_synced_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
