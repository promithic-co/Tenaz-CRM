<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lead sub-object shape for the conversation panel. Parity port of the
 * `$leadData` block in ConversasController::conversationProps (lead only —
 * NOT mensagens / handoff).
 *
 * Two fields depend on services / the batch resolver and are injected so this
 * resource stays a pure shape mapper:
 *   - $availableTransitions ← StatusMachine::getAvailableTransitions
 *   - $effectiveAiMode      ← ConversationAutomationService mode resolution
 *
 *   new ConversationResource($lead, $availableTransitions, $effectiveAiMode, $collectedInformation)
 *
 * Requires `whatsappInstance`, `tags` (with the source / ai_confidence
 * pivot) and `agent.config` eager-loaded. Emits `cpf` (preserved from legacy). Drops the pivot
 * `ai_evidence` / `ai_evaluated_at` columns (legacy drops them — kept dropped).
 *
 * @property-read Lead $resource
 */
class ConversationResource extends JsonResource
{
    /**
     * @param  array<int, string>  $availableTransitions
     */
    public function __construct(
        Lead $resource,
        private readonly array $availableTransitions,
        private readonly string $effectiveAiMode,
        private readonly array $collectedInformation,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lead = $this->resource;

        return [
            'id' => $lead->id,
            'agent_id' => $lead->agent_id,
            'contact_id' => $lead->contact_id,
            'nome' => $lead->nome ?? $lead->whatsapp,
            'whatsapp' => $lead->whatsapp,
            'cpf' => $lead->cpf,
            'idade' => $lead->idade,
            'status' => $lead->status,
            'available_transitions' => $this->availableTransitions,
            'ai_mode' => $lead->ai_mode,
            'effective_ai_mode' => $this->effectiveAiMode,
            'operational_stage' => $lead->operational_stage,
            'assigned_user_id' => $lead->assigned_user_id,
            'assigned_user_name' => $lead->assignedUser?->name,
            'ai_paused_until' => $lead->ai_paused_until?->toIso8601String(),
            'ai_paused_reason' => $lead->ai_paused_reason,
            'followup_count' => $lead->followup_count,
            'followup_status' => $lead->followup_status,
            'agent_niche' => $lead->agent?->config?->agent_niche ?? 'inss',
            'resumo_credito' => $lead->credito_json['resumoGeral']['textoResumo'] ?? null,
            'collected_information' => $this->collectedInformation,
            'tags' => $lead->tags->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'color' => $t->color,
                'source' => $t->pivot?->source,
                'ai_confidence' => $t->pivot?->ai_confidence !== null ? (float) $t->pivot->ai_confidence : null,
            ])->all(),
        ];
    }
}
