<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => 'default',
            'agent_id' => null,
            'whatsapp' => fake()->numerify('55119########'),
            'nome' => fake()->name(),
            'status' => 'novo',
            'modo' => 'receptivo',
            'ai_mode' => null,
            'operational_stage' => 'new_inbound',
            'is_sandbox' => false,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(['tenant_id' => $tenantId]);
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state([
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
        ]);
    }

    public function withoutAgent(): static
    {
        return $this->state([
            'agent_id' => null,
            'ai_mode' => null,
            'operational_stage' => 'human_pending',
        ]);
    }

    public function sandbox(): static
    {
        return $this->state([
            'is_sandbox' => true,
            'whatsapp' => 'sandbox_'.fake()->unique()->uuid(),
            'sandbox_label' => fake()->sentence(3),
        ]);
    }
}
