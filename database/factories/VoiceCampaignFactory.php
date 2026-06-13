<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VoiceCampaign>
 */
class VoiceCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_list_id' => \App\Models\ContactList::factory(),
            'name' => fake()->words(3, true),
            'status' => 'draft',
            'tts_voice' => 'Google.pt-BR-Standard-A',
            'dtmf_actions' => [
                '1' => ['action' => 'interested', 'label' => 'Tenho interesse'],
                '2' => ['action' => 'optout', 'label' => 'Não quero mais ligar'],
            ],
            'delay_between_calls_ms' => 3000,
            'total_calls' => 0,
            'total_answered' => 0,
            'total_interested' => 0,
            'total_no_answer' => 0,
            'total_failed' => 0,
        ];
    }
}
