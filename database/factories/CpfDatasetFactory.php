<?php

namespace Database\Factories;

use App\Models\CpfDataset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CpfDataset>
 */
class CpfDatasetFactory extends Factory
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
            'name' => fake()->words(3, true).' Dataset',
            'description' => fake()->sentence(),
            'total_entries' => 0,
        ];
    }
}
