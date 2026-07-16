<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\UraApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UraApiKey>
 */
class UraApiKeyFactory extends Factory
{
    public function definition(): array
    {
        $generated = UraApiKey::generate();

        return [
            'agent_id' => Agent::factory(),
            'tenant_id' => fn (array $attributes) => Agent::withoutGlobalScopes()
                ->findOrFail($attributes['agent_id'])
                ->tenant_id,
            'whatsapp_template_id' => null,
            'name' => fake()->words(3, true),
            'key_hash' => $generated['key_hash'],
            'key_preview' => $generated['key_preview'],
            'active' => true,
            'last_used_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
