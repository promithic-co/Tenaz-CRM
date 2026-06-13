<?php

namespace Database\Factories;

use App\Models\AiUsageDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageDaily>
 */
class AiUsageDailyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->date(),
            'tenant_id' => (string) fake()->randomDigitNotNull(),
            'agent_id' => null,
            'model' => fake()->randomElement(['gpt-4o', 'gpt-4o-mini', 'claude-sonnet-4-20250514']),
            'total_requests' => fake()->numberBetween(10, 500),
            'total_prompt_tokens' => fake()->numberBetween(5000, 500000),
            'total_completion_tokens' => fake()->numberBetween(2000, 200000),
            'estimated_cost_usd' => fake()->randomFloat(6, 0.01, 50.0),
        ];
    }
}
