<?php

namespace App\Http\Controllers;

use App\Actions\ClearLeadHistoryAction;
use App\Actions\SendOperatorMessageAction;
use App\Http\Requests\BulkTransferRequest;
use App\Http\Requests\InboxFilterRequest;
use App\Http\Requests\SendConversationMessageRequest;
use App\Http\Resources\AgentInteractionEventResource;
use App\Http\Resources\ConversationResource;
use App\Models\AgentInteractionEvent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\StatusMachine;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\AgentInteractionEventService;
use App\Services\ConversationAutomationService;
use App\Services\ConversationTimelineService;
use App\Services\ConversationTransferService;
use App\Services\HumanHandoffStateService;
use App\Services\PauseService;
use App\Services\ServiceTicketLifecycleService;
use App\Services\WhatsApp\WhatsAppConversationWindowResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConversasController extends Controller
{
    public function __construct(
        private readonly PauseService $pause,
        private readonly ConversationTimelineService $timeline,
    ) {}

    public function index(InboxFilterRequest $request): Response
    {
        return Inertia::render('conversas/Index', $this->inboxProps($request));
    }

    public function show(InboxFilterRequest $request, Lead $lead): Response
    {
        $this->authorize('view', $lead);

        return Inertia::render('conversas/Index', $this->inboxProps($request, $lead));
    }

    /** @param array<string, mixed> $filters */
    private function buildInboxQuery(array $filters, string $tenantId): Builder
    {
        $query = Lead::production()->forTenant($tenantId)->with(['assignedUser', 'tags:id,name,color,slug,is_hot']);

        if ($filters['instance'] !== '') {
            $instanceId = WhatsappInstance::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $filters['instance'])
                ->value('id');

            $query->where('whatsapp_instance_id', $instanceId ?? 0);
        }

        // Always scope to what the actor may see — a restricted user must not bypass
        // visibility by selecting an instance filter.
        return $query->visibleTo(auth()->user())->inboxFiltered($filters);
    }

    /**
     * @return array{
     *     leads: mixed,
     *     filters: array<string, string>,
     *     instances: mixed,
     *     activeConversation: array<string, mixed>|null
     * }
     */
    private function inboxProps(InboxFilterRequest $request, ?Lead $activeLead = null): array
    {
        $filters = $request->filters();
        $tenantId = (string) auth()->user()->tenantId;

        $instances = WhatsappInstance::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('display_name')
            ->orderBy('name')
            ->get(['name', 'display_name']);

        $paginator = $this->buildInboxQuery($filters, $tenantId)
            ->paginate(15)
            ->withQueryString();

        $pageLeads = $paginator->getCollection();

        // Bulk-resolve effective AI modes for the page (eliminates N+1 inside through()).
        $effectiveModes = app(ConversationAutomationService::class)->resolveModesByInstanceId($pageLeads);

        // Bulk-resolve pause state using a single Cache::many() instead of N Cache::has() calls.
        $pauseKeys = $pageLeads->map(fn ($l) => "pause:{$tenantId}:{$l->whatsapp}")->all();
        $pauseCacheValues = $pauseKeys === [] ? [] : Cache::many($pauseKeys);
        $pauseMap = [];
        foreach ($pageLeads as $l) {
            $cached = $pauseCacheValues["pause:{$tenantId}:{$l->whatsapp}"] ?? null;
            $pauseMap[$l->whatsapp] = $cached !== null
                || ($l->ai_paused_until !== null && $l->ai_paused_until->isFuture());
        }

        $leads = $paginator->through(fn ($l) => [
            'id' => $l->id,
            'nome' => $l->nome ?? $l->whatsapp,
            'whatsapp' => $l->whatsapp,
            'status' => $l->status,
            'followup_status' => $l->followup_status,
            'followup_count' => $l->followup_count,
            'ai_mode' => $l->ai_mode,
            'effective_ai_mode' => $effectiveModes[$l->id],
            'operational_stage' => $l->operational_stage,
            'assigned_user_id' => $l->assigned_user_id,
            'assigned_user_name' => $l->assignedUser?->name,
            'ultima_interacao' => $l->last_interaction_at?->diffForHumans() ?? $l->created_at?->diffForHumans(),
            'pausado' => $pauseMap[$l->whatsapp] ?? false,
            'whatsapp_instance_id' => $l->whatsapp_instance_id,
        ]);

        $transferTargets = $this->transferTargetsFor($tenantId);

        return [
            'leads' => $leads,
            'filters' => $filters,
            'instances' => $instances->map(fn (WhatsappInstance $instance) => [
                'name' => $instance->name,
                'label' => $instance->label(),
            ]),
            'transfer_targets' => $transferTargets,
            'activeConversation' => $activeLead ? $this->conversationProps($activeLead) : null,
        ];
    }

    /**
     * @return array{
     *     lead: array<string, mixed>,
     *     mensagens: array<int, array<string, mixed>>,
     *     pausado: bool,
     *     followupStatus: string,
     *     followupHistory: mixed
     * }
     */
    private function conversationProps(Lead $lead): array
    {
        // Timeline is the single source of truth for the conversation UI. The legacy
        // fallback to agent_conversation_messages was removed in Phase 45 — that table
        // is now a derived mirror managed by ConversationContextSynchronizer and must
        // not be read directly by the UI layer.
        $mensagens = $this->timeline->forLead($lead);

        // Legacy backfill: if timeline is empty but a laravel/ai conversation exists,
        // synthesize a minimal view so old leads (pre-timeline) don't show as empty.
        if ($mensagens === [] && $lead->conversation_id) {
            $mensagens = $this->timeline->legacyMessages($lead);
        }

        $lead->load([
            'whatsappInstance',
            'tags' => fn ($q) => $q->withPivot('source', 'ai_confidence', 'ai_evidence', 'ai_evaluated_at'),
        ]);

        $availableTransitions = StatusMachine::forTenant((string) $lead->tenant_id)
            ->getAvailableTransitions((string) $lead->status);
        $effectiveAiMode = app(ConversationAutomationService::class)
            ->resolveModesByInstanceId(collect([$lead]))[$lead->id];

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

        $isPrivileged = auth()->user()?->isOwnerOrAdmin() ?? false;

        $handoffState = app(HumanHandoffStateService::class);
        $activeTicket = ServiceTicket::query()->activeEscalation($lead->id)->with('assignedUser')->latest()->first();
        $activeHandoff = $activeTicket ? $handoffState->activeHandoffPayload($lead) : null;
        $derivedState = $handoffState->deriveState($lead, $activeTicket);
        $handoffActions = $handoffState->handoffActions($activeTicket);

        $transferTargets = $this->transferTargetsFor((string) $lead->tenant_id);

        return [
            'lead' => $leadData,
            'mensagens' => $mensagens,
            'pausado' => $this->pause->isPaused($lead->whatsapp, $lead->tenant_id),
            'followupStatus' => $lead->followup_status,
            'followupHistory' => $followupHistory,
            'conversationWindow' => app(WhatsAppConversationWindowResolver::class)->resolve($lead),
            'recentEvents' => $recentEvents,
            'canStartCampaign' => $isPrivileged,
            'active_handoff' => $activeHandoff,
            'handoff_state' => $derivedState,
            'handoff_actions' => $handoffActions,
            'transfer_targets' => $transferTargets,
        ];
    }

    public function preview(Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        $messages = $this->timeline->legacyMessages($lead, limit: 5, newestFirst: true);

        $timelineMessages = $this->timeline->forLead($lead);
        if ($timelineMessages !== []) {
            $messages = array_slice($timelineMessages, -5);
        }

        return response()->json([
            'lead' => [
                'id' => $lead->id,
                'nome' => $lead->nome,
                'whatsapp' => $lead->whatsapp,
                'cpf' => $lead->cpf,
                'idade' => $lead->idade,
                'status' => $lead->status,
                'ai_mode' => $lead->ai_mode,
                'effective_ai_mode' => app(ConversationAutomationService::class)->resolveModesByInstanceId(collect([$lead]))[$lead->id],
                'operational_stage' => $lead->operational_stage,
                'followup_count' => $lead->followup_count,
                'followup_status' => $lead->followup_status,
                'credito_json' => $lead->credito_json,
                'ultima_interacao' => $lead->last_interaction_at?->diffForHumans() ?? $lead->created_at?->diffForHumans(),
            ],
            'messages' => $messages,
        ]);
    }

    public function pause(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $this->pause->pause($lead->whatsapp, $lead->tenant_id);
        app(ConversationAutomationService::class)->pauseForHuman($lead, auth()->user(), 'manual_pause');

        $events = app(AgentInteractionEventService::class);
        $events->recordForLead(
            interactionId: $events->newInteractionId(),
            lead: $lead,
            eventType: 'ai_paused_manual',
            eventSource: 'conversas_controller',
            payload: ['user_id' => auth()->id()],
        );

        return back();
    }

    public function resume(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $this->pause->resume($lead->whatsapp, $lead->tenant_id);
        app(ConversationAutomationService::class)->resumeAi($lead);

        $events = app(AgentInteractionEventService::class);
        $events->recordForLead(
            interactionId: $events->newInteractionId(),
            lead: $lead,
            eventType: 'ai_resumed_manual',
            eventSource: 'conversas_controller',
            payload: ['user_id' => auth()->id()],
        );

        return back();
    }

    /**
     * Canonical claim action: finds or creates an active escalation ticket,
     * assigns ticket + lead to the current user atomically, pauses AI.
     */
    public function claim(Lead $lead): RedirectResponse
    {
        $this->authorize('assume', $lead);

        $user = auth()->user();
        $lifecycle = app(ServiceTicketLifecycleService::class);

        try {
            $lifecycle->claimByLead($lead, $user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('flash', $e->getMessage());
        }

        $events = app(AgentInteractionEventService::class);
        $events->recordForLead(
            interactionId: $events->newInteractionId(),
            lead: $lead,
            eventType: 'handoff_claimed',
            eventSource: 'conversas_controller',
            payload: ['user_id' => $user->id],
        );

        return back()->with('flash', 'Atendimento assumido.');
    }

    /**
     * Legacy assume action — delegates to the canonical claim path.
     */
    public function assume(Lead $lead): RedirectResponse
    {
        return $this->claim($lead);
    }

    /**
     * Bulk-transfer selected conversations to a specific user.
     * Creates or reuses the active escalation ticket, assigns ticket + lead atomically.
     * Returns applied/ignored counts in the flash message.
     */
    public function bulkTransfer(BulkTransferRequest $request): RedirectResponse
    {
        $actor = auth()->user();
        $tenantId = (string) $actor->tenantId;

        $targetUser = User::findOrFail($request->integer('target_id'));

        if ((string) $targetUser->tenantId !== $tenantId) {
            return back()->withErrors(['target_id' => 'Usuário de destino não pertence ao tenant.']);
        }

        $leads = Lead::production()
            ->forTenant($tenantId)
            ->whereIn('id', $request->input('lead_ids', []))
            ->get();

        $transfer = app(ConversationTransferService::class);
        $events = app(AgentInteractionEventService::class);
        $applied = 0;
        $ignored = 0;

        foreach ($leads as $lead) {
            try {
                $this->authorize('assume', $lead);
                $transfer->transferToUser($lead, $actor, $targetUser);
                $events->recordForLead(
                    interactionId: $events->newInteractionId(),
                    lead: $lead,
                    eventType: 'handoff_claimed',
                    eventSource: 'conversas_bulk_transfer',
                    payload: ['actor_id' => $actor->id, 'target_user_id' => $targetUser->id],
                );
                $applied++;
            } catch (\Throwable $e) {
                Log::warning('Bulk transfer skipped a lead', [
                    'lead_id' => $lead->id,
                    'target_user_id' => $targetUser->id,
                    'error' => $e->getMessage(),
                ]);
                $ignored++;
            }
        }

        $flash = $applied > 0
            ? "{$applied} conversa(s) transferida(s) para {$targetUser->name}.".($ignored > 0 ? " {$ignored} ignorada(s)." : '')
            : 'Nenhuma conversa foi transferida.';

        return back()->with('flash', $flash);
    }

    public function updateAiMode(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $data = $request->validate([
            'ai_mode' => ['nullable', 'string', 'in:automatic,manual,assisted,qualify_then_handoff'],
        ]);

        $lead->update(['ai_mode' => $data['ai_mode'] ?? null]);

        return back()->with('flash', 'Modo de IA atualizado.');
    }

    /**
     * Delete lead's conversation history and agent memory (conversation + messages +
     * follow-up history). Does not delete the lead or change status/cpf/etc. Wrapped
     * in a transaction so partial wipes can't leave context corrupted.
     */
    public function clearHistory(Lead $lead, ClearLeadHistoryAction $clearHistory): RedirectResponse
    {
        $this->authorize('update', $lead);

        $events = app(AgentInteractionEventService::class);
        $interactionId = $events->newInteractionId();

        $clearHistory->clearForLead($lead);

        $events->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'history_cleared_manual',
            eventSource: 'conversas_controller',
            payload: ['user_id' => auth()->id()],
            severity: 'warning',
        );

        return back()->with('flash', 'Histórico e memória do lead foram apagados.');
    }

    /**
     * Operator sends a message (text and/or file) to the lead's WhatsApp.
     */
    public function sendMessage(
        SendConversationMessageRequest $request,
        Lead $lead,
        SendOperatorMessageAction $sendMessage,
    ): JsonResponse {
        $result = $sendMessage->send(
            lead: $lead,
            content: $request->input('content'),
            file: $request->file('file'),
            actor: auth()->user(),
            broadcastToOthers: (bool) $request->header('X-Socket-ID'),
        );

        if ($result === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nenhuma instância de WhatsApp associada a este lead.',
            ], 422);
        }

        return response()->json([
            'status' => 'queued',
            'message' => $result['message'],
            'outbox_id' => $result['outbox_id'],
        ]);
    }

    /**
     * Privileged-only transfer targets for a tenant: the tenant's members
     * projected to the frontend `{type,id,name}` shape, or an empty list when
     * the current user is not an owner/admin. Dedupes the inbox + conversation
     * panel target lists.
     *
     * @return list<array{type: string, id: int, name: string}>
     */
    private function transferTargetsFor(string $tenantId): array
    {
        if (! (auth()->user()?->isOwnerOrAdmin() ?? false)) {
            return [];
        }

        return User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['type' => 'user', 'id' => $u->id, 'name' => $u->name])
            ->all();
    }
}
