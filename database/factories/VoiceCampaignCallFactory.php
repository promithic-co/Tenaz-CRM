<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VoiceCampaignCall>
 */
class VoiceCampaignCallFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone' => '+5511'.fake()->numerify('#########'),
            'contact_name' => fake()->name(),
            'interpolated_message' => 'Olá '.fake()->firstName().', aqui é da empresa. Pressione 1 para saber mais.',
            'status' => 'pending',
        ];
    }
}
