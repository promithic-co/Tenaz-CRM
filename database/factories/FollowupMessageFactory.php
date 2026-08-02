<?php

namespace Database\Factories;

use App\Models\FollowupMessage;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowupMessage>
 */
class FollowupMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'attempt' => 1,
            'message_text' => fake()->sentence(),
            'tone' => fake()->randomElement(['amigavel', 'natural', 'persuasivo']),
            'sent_at' => now(),
            'status' => 'sent',
        ];
    }
}
