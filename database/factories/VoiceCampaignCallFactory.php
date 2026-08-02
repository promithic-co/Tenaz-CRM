<?php

namespace Database\Factories;

use App\Models\VoiceCampaignCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoiceCampaignCall>
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
