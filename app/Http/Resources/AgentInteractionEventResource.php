<?php

namespace App\Http\Resources;

use App\Models\AgentInteractionEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recent manual-event row shape for the conversation panel. Parity port of the
 * `recentEvents` columns selected in ConversasController::conversationProps
 * (event_type / created_at / severity / payload_json).
 *
 * Legacy passed the raw model collection (selected columns) straight to
 * Inertia, so the wire shape is the model's own attribute serialization. This
 * resource reproduces that: `created_at` carries the model's datetime cast and
 * `payload_json` its array cast.
 *
 * @property-read AgentInteractionEvent $resource
 */
class AgentInteractionEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'event_type' => $this->resource->event_type,
            'created_at' => $this->resource->created_at,
            'severity' => $this->resource->severity,
            'payload_json' => $this->resource->payload_json,
        ];
    }
}
