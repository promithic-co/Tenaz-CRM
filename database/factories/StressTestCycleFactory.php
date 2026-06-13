<?php

namespace Database\Factories;

use App\Models\StressTestRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StressTestCycle>
 */
class StressTestCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stress_test_run_id' => StressTestRun::factory(),
            'cycle_number' => 1,
            'cpf_used' => null,
            'scenario' => null,
            'lead_id' => null,
            'status' => 'pending',
            'fidelity_score' => null,
            'hallucinations' => null,
            'token_metrics' => null,
            'evaluation_report' => null,
            'console_errors' => null,
            'completed_at' => null,
        ];
    }
}
