<?php

namespace App\Http\Resources;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List shape for an Agent. Parity port of the AgentsController::index mapper.
 *
 * The per-agent aggregates (leads_count / active_conversations /
 * converted_count / conversion_rate) are NOT computed here — they come from
 * the F.3 `withCount` aggregates. They are read off the model attributes,
 * falling back to the values supplied via the resource's additional metadata
 * (`->additional([...])`) when present, so this resource emits the final flat
 * shape regardless of how the aggregates are sourced.
 *
 * Expects the `config` and `whatsappInstance` relations to be loaded.
 *
 * @property-read Agent $resource
 */
class AgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $agent = $this->resource;

        $leadsCount = (int) ($this->aggregate('leads_count') ?? 0);
        $converted = (int) ($this->aggregate('converted_count') ?? 0);
        $agentNiche = $agent->config?->agent_niche ?? 'inss';
        $specialization = config("credflow.agent_specializations.{$agentNiche}")
            ?? config('credflow.agent_specializations.inss')
            ?? [];

        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'description' => $agent->description,
            'is_active' => $agent->is_active,
            'is_default' => $agent->is_default,
            'display_agent_name' => $agent->config?->agent_name,
            'model' => $agent->config?->agent_model,
            'provider' => $agent->config?->agent_provider,
            'template_slug' => $agent->config?->template_slug,
            'agent_niche' => $agentNiche,
            'specialization' => [
                'value' => $agentNiche,
                'label' => $specialization['label'] ?? strtoupper($agentNiche),
                'description' => $specialization['description'] ?? '',
                'badge_classes' => $specialization['badge_classes'] ?? 'border-border bg-muted text-muted-foreground',
            ],
            'instance' => $agent->whatsappInstance ? [
                'id' => $agent->whatsappInstance->id,
                'name' => $agent->whatsappInstance->name,
                'display_name' => $agent->whatsappInstance->display_name,
                'phone_number' => $agent->whatsappInstance->phone_number,
            ] : null,
            'leads_count' => $leadsCount,
            'active_conversations' => (int) ($this->aggregate('active_conversations') ?? 0),
            'converted_count' => $converted,
            'conversion_rate' => $this->aggregate('conversion_rate')
                ?? ($leadsCount > 0 ? round(($converted / $leadsCount) * 100) : 0),
        ];
    }

    /**
     * Resolve an aggregate value from the model attributes first, then from the
     * resource's `additional()` metadata.
     */
    private function aggregate(string $key): mixed
    {
        $value = $this->resource->getAttribute($key);

        if ($value !== null) {
            return $value;
        }

        return $this->additional[$key] ?? null;
    }
}
