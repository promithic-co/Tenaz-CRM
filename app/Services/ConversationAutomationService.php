<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ConversationAutomationService
{
    /**
     * Batch-resolve the effective AI mode for a collection of leads keyed by the
     * integer `whatsapp_instance_id` relation. Mirrors
     * ConversasController::resolveModeWithCache (fallback guarded on instance presence).
     *
     * @param  Collection<int, Lead>  $leads
     * @return array<int, string> [leadId => effectiveMode]
     */
    public function resolveModesByInstanceId(Collection $leads): array
    {
        $instanceIds = $leads->pluck('whatsapp_instance_id')->filter()->unique()->values();

        $defaultModesByInstance = $instanceIds->isEmpty()
            ? collect()
            : WhatsappInstance::withoutGlobalScope('tenant')
                ->whereIn('id', $instanceIds)
                ->pluck('default_ai_mode', 'id');

        $resolved = [];

        foreach ($leads as $lead) {
            $resolved[$lead->id] = $this->resolveModeViaInstanceId($lead, $defaultModesByInstance);
        }

        return $resolved;
    }

    /**
     * Batch-resolve the effective AI mode for a collection of leads keyed by the
     * string `evolution_instance` name. Null-coalescing fallback, no guard: only
     * a NULL `ai_mode` falls through to the instance default. Canonical resolver
     * consumed by PipelineController::toCardShape.
     *
     * @param  Collection<int, Lead>  $leads
     * @return array<int, string> [leadId => effectiveMode]
     */
    public function resolveModesByInstanceName(Collection $leads): array
    {
        $names = $leads->pluck('evolution_instance')->filter()->unique()->values();

        $defaultModesByInstance = $names->isEmpty()
            ? collect()
            : WhatsappInstance::withoutGlobalScope('tenant')
                ->whereIn('name', $names)
                ->pluck('default_ai_mode', 'name');

        $resolved = [];

        foreach ($leads as $lead) {
            $resolved[$lead->id] = $this->resolveModeViaInstanceName($lead, $defaultModesByInstance);
        }

        return $resolved;
    }

    /**
     * Instance-id keyed resolution. Fallback to the instance default is guarded on
     * instance presence and triggered for any falsy `ai_mode` (null OR empty string),
     * matching ConversasController::resolveModeWithCache.
     *
     * @param  Collection<int, string>  $defaultModesByInstance
     */
    private function resolveModeViaInstanceId(Lead $lead, Collection $defaultModesByInstance): string
    {
        if ($lead->agent_id === null) {
            return Lead::AI_MODE_MANUAL;
        }

        $mode = $lead->ai_mode;

        if (! $mode && $lead->whatsapp_instance_id) {
            $mode = $defaultModesByInstance->get($lead->whatsapp_instance_id);
        }

        return $this->validatedMode($mode);
    }

    /**
     * Instance-name keyed resolution. Fallback to the instance default is via null
     * coalescing (only a NULL `ai_mode` falls through; an empty string is kept).
     *
     * @param  Collection<string, string>  $defaultModesByInstance
     */
    private function resolveModeViaInstanceName(Lead $lead, Collection $defaultModesByInstance): string
    {
        if ($lead->agent_id === null) {
            return Lead::AI_MODE_MANUAL;
        }

        $mode = $lead->ai_mode ?? $defaultModesByInstance->get($lead->evolution_instance);

        return $this->validatedMode($mode);
    }

    /**
     * Canonical validation tail shared by every resolver: an unrecognised mode
     * (including null or empty string) collapses to AUTOMATIC.
     */
    private function validatedMode(?string $mode): string
    {
        return in_array($mode, Lead::AI_MODES, true) ? $mode : Lead::AI_MODE_AUTOMATIC;
    }

    public function resolveMode(Lead $lead, ?string $instanceName = null): string
    {
        // CRM-first: a lead with no agent is by definition not AI-driven.
        // Force manual regardless of instance default so a CRM-only inbound
        // is never silently routed through automation.
        if ($lead->agent_id === null) {
            return Lead::AI_MODE_MANUAL;
        }

        $mode = $lead->ai_mode;

        if (! $mode && $instanceName) {
            $mode = WhatsappInstance::withoutGlobalScope('tenant')
                ->where('name', $instanceName)
                ->value('default_ai_mode');
        }

        return in_array($mode, Lead::AI_MODES, true) ? $mode : Lead::AI_MODE_AUTOMATIC;
    }

    public function shouldAutoRespond(Lead $lead, ?string $instanceName = null): bool
    {
        if ($this->isPaused($lead)) {
            return false;
        }

        return in_array($this->resolveMode($lead, $instanceName), [
            Lead::AI_MODE_AUTOMATIC,
            Lead::AI_MODE_QUALIFY_THEN_HANDOFF,
        ], true);
    }

    public function isPaused(Lead $lead): bool
    {
        return $lead->ai_paused_until !== null && $lead->ai_paused_until->isFuture();
    }

    /**
     * @param  array<string, mixed>|null  $referral
     */
    public function markInbound(Lead $lead, string $mode, ?array $referral = null): void
    {
        $now = now();
        $updates = [
            'last_interaction_at' => $now,
            'last_inbound_at' => $now,
            'service_window_expires_at' => $now->copy()->addHours(FollowUpWindowService::CUSTOMER_SERVICE_WINDOW_HOURS),
        ];

        $freeEntryPointSource = $this->freeEntryPointSource($referral);
        if ($freeEntryPointSource !== null) {
            $updates['free_entry_point_started_at'] = $now;
            $updates['free_entry_point_expires_at'] = $now->copy()->addHours(FollowUpWindowService::FREE_ENTRY_POINT_WINDOW_HOURS);
            $updates['conversation_window_source'] = $freeEntryPointSource;
        }

        if ($this->isPaused($lead)) {
            $updates['operational_stage'] = Lead::STAGE_HUMAN_ACTIVE;
        } elseif (in_array($mode, [Lead::AI_MODE_MANUAL, Lead::AI_MODE_ASSISTED], true)) {
            $updates['operational_stage'] = $lead->assigned_user_id
                ? Lead::STAGE_HUMAN_ACTIVE
                : Lead::STAGE_HUMAN_PENDING;
        } elseif (! in_array($lead->status, ['convertido', 'desqualificado', 'optou_sair', 'sem_credito'], true)) {
            $updates['operational_stage'] = Lead::STAGE_AI_QUALIFYING;
        }

        $lead->updateQuietly($updates);
        $lead->refresh();
    }

    /**
     * @param  array<string, mixed>|null  $referral
     */
    private function freeEntryPointSource(?array $referral): ?string
    {
        if ($referral === null || $referral === []) {
            return null;
        }

        $sourceType = strtolower((string) ($referral['source_type'] ?? ''));

        if (($referral['ctwa_clid'] ?? null) !== null || in_array($sourceType, ['ad', 'ctwa', 'click_to_whatsapp_ad'], true)) {
            return 'ctwa_ad';
        }

        if (in_array($sourceType, ['post', 'page', 'page_cta', 'facebook_page'], true)) {
            return 'page_cta';
        }

        return null;
    }

    public function pauseForHuman(
        Lead $lead,
        ?User $user = null,
        string $reason = 'human_takeover',
        ?CarbonInterface $until = null,
    ): void {
        $updates = [
            'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
            'ai_paused_until' => $until ?? now()->addHours(10),
            'ai_paused_reason' => $reason,
        ];

        if ($user) {
            $updates['assigned_user_id'] = $lead->assigned_user_id ?? $user->id;
            $updates['ai_paused_by'] = $user->id;
        }

        if ($lead->followup_status === 'active') {
            $updates['followup_status'] = 'paused';
        }

        $lead->update($updates);
    }

    public function resumeAi(Lead $lead): void
    {
        $updates = [
            'ai_paused_until' => null,
            'ai_paused_reason' => null,
            'ai_paused_by' => null,
        ];

        if ($lead->operational_stage === Lead::STAGE_HUMAN_ACTIVE) {
            $updates['operational_stage'] = match ($lead->status) {
                'qualificado' => Lead::STAGE_QUALIFIED_OPPORTUNITY,
                'sem_credito' => Lead::STAGE_FUTURE_OPPORTUNITY,
                'convertido' => Lead::STAGE_WON,
                'desqualificado', 'optou_sair' => Lead::STAGE_LOST,
                default => Lead::STAGE_NEW_INBOUND,
            };
        }

        $lead->update($updates);
    }

    public function syncAfterAgentTurn(Lead $lead, string $mode): void
    {
        $lead->refresh();

        if ($mode === Lead::AI_MODE_QUALIFY_THEN_HANDOFF && $lead->status === 'qualificado') {
            app(HumanHandoffTransferService::class)->transferFromAi($lead, [
                'reason' => 'proposta_aceita',
                'summary' => 'Lead qualificado automaticamente. Aguardando atendente humano para conduzir a proposta.',
                'metadata' => ['source' => 'qualify_then_handoff'],
            ]);

            return;
        }

        $stage = match (true) {
            $lead->status === 'qualificado' => Lead::STAGE_QUALIFIED_OPPORTUNITY,
            $lead->followup_status === 'active' => Lead::STAGE_AI_FOLLOWUP,
            $lead->status === 'sem_credito' => Lead::STAGE_FUTURE_OPPORTUNITY,
            $lead->status === 'convertido' => Lead::STAGE_WON,
            in_array($lead->status, ['desqualificado', 'optou_sair'], true) => Lead::STAGE_LOST,
            $lead->status === 'escalado' => Lead::STAGE_HUMAN_PENDING,
            default => Lead::STAGE_AI_QUALIFYING,
        };

        if ($lead->operational_stage !== $stage) {
            $lead->update(['operational_stage' => $stage]);
        }
    }
}
