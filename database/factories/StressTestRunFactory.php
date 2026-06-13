<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StressTestRun>
 */
class StressTestRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cpf_dataset_id' => null,
            'label' => 'Teste '.fake()->words(2, true),
            'objective' => fake()->sentence(),
            'config' => ['cycles' => 5, 'rounds_per_cycle' => 3],
            'status' => 'pending',
            'total_cycles' => 5,
            'completed_cycles' => 0,
            'results_summary' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
