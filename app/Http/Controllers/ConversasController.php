<?php

namespace App\Http\Controllers;

use App\Actions\ClearLeadHistoryAction;
use App\Actions\SendOperatorMessageAction;
use App\Actions\SendOperatorTemplateAction;
use App\Http\Requests\BulkTransferRequest;
use App\Http\Requests\InboxFilterRequest;
use App\Http\Requests\SendConversationMessageRequest;
use App\Http\Requests\UpdateLeadCollectedInformationRequest;
use App\Jobs\SyncMetaTemplatesJob;
use App\Models\Lead;
use App\Models\User;
use App\Services\AgentInteractionEventService;
use App\Services\ContactCollectedInformationService;
use App\Services\ConversationAutomationService;
use App\Services\ConversationInboxPropsBuilder;
use App\Services\ConversationTimelineService;
use App\Services\ConversationTransferService;
use App\Services\PauseService;
use App\Services\ServiceTicketLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConversasController extends Controller
{
    public function __construct(
        private readonly PauseService $pause,
        private readonly ConversationTimelineService $timeline,
        private readonly ConversationInboxPropsBuilder $inboxProps,
    ) {}

    public function index(InboxFilterRequest $request): Response
    {
        return Inertia::render('conversas/Index', $this->inboxProps->build($request));
    }

    public function show(InboxFilterRequest $request, Lead $lead): Response
    {
        $this->authorize('view', $lead);

        return Inertia::render('conversas/Index', $this->inboxProps->build($request, $lead));
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

        $previousAiMode = $lead->ai_mode;
        $previousPauseReason = $lead->ai_paused_reason;

        $this->pause->resume($lead->whatsapp, $lead->tenant_id);
        app(ConversationAutomationService::class)->resumeAi($lead);
        $lead->refresh();

        Log::info('conversation.ai_resumed', [
            'lead_id' => $lead->id,
            'tenant_id' => $lead->tenant_id,
            'previous_ai_mode' => $previousAiMode,
            'previous_pause_reason' => $previousPauseReason,
            'ai_mode' => $lead->ai_mode,
            'is_ai_paused' => $lead->isAiPaused(),
        ]);

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

    public function updateCollectedInformation(
        UpdateLeadCollectedInformationRequest $request,
        Lead $lead,
        ContactCollectedInformationService $information,
    ): RedirectResponse {
        $contact = $information->resolveForLead($lead);

        if ($contact === null) {
            throw ValidationException::withMessages([
                'label' => 'Não foi possível vincular este lead a um contato.',
            ]);
        }

        $information->applyManual($contact, $request->validated());

        return back()->with('flash', 'Informações do contato atualizadas.');
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
        SendOperatorTemplateAction $sendTemplate,
    ): JsonResponse {
        $templateId = $request->integer('template_id');

        $result = $templateId > 0
            ? $sendTemplate->send(
                lead: $lead,
                templateId: $templateId,
                parameters: (array) $request->input('template_parameters', []),
                actor: auth()->user(),
                broadcastToOthers: (bool) $request->header('X-Socket-ID'),
            )
            : $sendMessage->send(
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
     * Queue a Meta template re-sync for the instance behind this conversation, so an operator who
     * just had a template approved can pull it without leaving the panel. The tenant-wide sync
     * endpoint requires an explicit instance id and admin rights; here the conversation itself
     * names the instance, and anyone who can act on the lead can refresh it.
     */
    public function syncTemplates(Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $instance = $lead->whatsappInstance;

        if ($instance === null || ($instance->provider?->value ?? null) !== 'meta_cloud' || empty($instance->meta_waba_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Esta conversa não está ligada a uma instância Meta Cloud com WABA configurado.',
            ], 422);
        }

        SyncMetaTemplatesJob::dispatch($instance->id);

        return response()->json(['status' => 'queued']);
    }
}
