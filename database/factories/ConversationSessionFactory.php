<?php

namespace Database\Factories;

use App\Models\ConversationSession;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationSession>
 */
class ConversationSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lead = Lead::factory();

        return [
            'lead_id' => $lead,
            'tenant_id' => fn (array $attributes) => (string) Lead::find($attributes['lead_id'])?->tenant_id,
            'number' => 1,
            'status' => ConversationSession::STATUS_OPEN,
            'open_reason' => ConversationSession::OPEN_REASON_FIRST_CONTACT,
            'outcome' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'last_message_at' => now(),
            'metadata' => null,
        ];
    }

    public function forLead(Lead $lead): static
    {
        return $this->state([
            'lead_id' => $lead->id,
            'tenant_id' => (string) $lead->tenant_id,
        ]);
    }

    public function open(): static
    {
        return $this->state([
            'status' => ConversationSession::STATUS_OPEN,
            'closed_at' => null,
            'outcome' => null,
        ]);
    }

    public function closed(string $outcome = ConversationSession::OUTCOME_LOST): static
    {
        return $this->state([
            'status' => ConversationSession::STATUS_CLOSED,
            'closed_at' => now(),
            'outcome' => $outcome,
        ]);
    }

    public function reengagement(): static
    {
        return $this->state([
            'open_reason' => ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL,
        ]);
    }
}
