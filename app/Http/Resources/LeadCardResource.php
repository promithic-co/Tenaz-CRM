<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pipeline card shape for a Lead. Parity port of
 * PipelineController::toCardShape.
 *
 * The effective AI mode / automation state and the source label are NOT
 * computed here — they depend on the batch instance-mode resolver and the
 * campaign relation. Callers resolve them up front and inject them so this
 * resource stays a pure shape mapper:
 *
 *   new LeadCardResource($lead, $automationState, $sourceLabel)
 *
 * Expects `$lead->tags` to be loaded for the tag sub-array.
 *
 * @property-read Lead $resource
 */
class LeadCardResource extends JsonResource
{
    public function __construct(
        Lead $resource,
        private readonly string $automationState,
        private readonly string $sourceLabel,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'contact_id' => $this->resource->contact_id,
            'nome' => $this->resource->nome,
            'whatsapp' => $this->resource->whatsapp,
            'status' => $this->resource->status,
            'automation_state' => $this->automationState,
            'followup_status' => $this->resource->followup_status,
            'source_label' => $this->sourceLabel,
            'last_message' => mb_substr((string) ($this->resource->last_message_preview ?? ''), 0, 60),
            'last_interaction_at' => $this->resource->last_interaction_at?->toIso8601String(),
            'sla_due_at' => $this->resource->sla_due_at?->toIso8601String() ?? null,
            'tags' => $this->resource->tags?->map(fn ($tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
                'is_hot' => (bool) ($tag->is_hot ?? false),
            ])->values()->all(),
        ];
    }
}
