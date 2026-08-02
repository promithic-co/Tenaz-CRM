<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VoiceInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoiceInstance>
 */
class VoiceInstanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->slug(2),
            'display_name' => fake()->company(),
            'post_call_meta_template_id' => null,
            'greeting_template' => 'Olá {nome}, aqui é da empresa. Pressione 1 para saber mais sobre nossas ofertas.',
            'post_call_message' => 'Olá! Obrigado pelo interesse. Nossa equipe entrará em contato em breve.',
            'active' => true,
        ];
    }
}
