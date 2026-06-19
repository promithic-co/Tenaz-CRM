<?php

namespace App\Services;

use App\Http\Resources\AgentInteractionEventResource;
use App\Http\Resources\ConversationResource;
use App\Models\AgentInteractionEvent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\StatusMachine;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppConversationWindowResolver;

class ConversationPanelPropsBuilder
{
    public function __construct(
        private readonly PauseService $pause,
        private readonly ConversationTimelineService $timeline,
        private readonly ConversationAutomationService $automation,
        private readonly HumanHandoffStateService $handoffState,
        private readonly WhatsAppConversationWindowResolver $conversationWindow,
        private readonly ConversationTransferTargetsBuilder $transferTargets,
    ) {}

    /**
     * @return array{
     *     lead: array<string, mixed>,
     *     mensagens: array<int, array<string, mixed>>,
     *     pausado: bool,
     *     followupStatus: string|null,
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
            'whatsappInstance',
            'tags' => fn ($query) => $query->withPivot('source', 'ai_confidence', 'ai_evidence', 'ai_evaluated_at'),
        ]);

        $availableTransitions = StatusMachine::forTenant((string) $lead->tenant_id)
            ->getAvailableTransitions((string) $lead->status);
        $effectiveAiMode = $this->automation->resolveModesByInstanceId(collect([$lead]))[$lead->id];

        $leadData = (new ConversationResource($lead, $availableTransitions, $effectiveAiMode))
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

        return [
            'lead' => $leadData,
            'mensagens' => $messages,
            'pausado' => $this->pause->isPaused($lead->whatsapp, $lead->tenant_id),
            'followupStatus' => $lead->followup_status,
            'followupHistory' => $followupHistory,
            'conversationWindow' => $this->conversationWindow->resolve($lead),
            'recentEvents' => $recentEvents,
            'canStartCampaign' => $actor->isOwnerOrAdmin(),
            'active_handoff' => $activeHandoff,
            'handoff_state' => $this->handoffState->deriveState($lead, $activeTicket),
            'handoff_actions' => $this->handoffState->handoffActions($activeTicket),
            'transfer_targets' => $this->transferTargets->forTenant((string) $lead->tenant_id, $actor),
        ];
    }
}
