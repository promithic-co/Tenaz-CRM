<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\UraApiKey;
use App\Models\User;
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
            'tenant_id' => User::factory(),
            'agent_id' => Agent::factory(),
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
