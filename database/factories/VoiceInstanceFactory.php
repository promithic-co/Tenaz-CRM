<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VoiceInstance>
 */
class VoiceInstanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => fake()->slug(2),
            'display_name' => fake()->company(),
            'post_call_meta_template_id' => null,
            'greeting_template' => 'Olá {nome}, aqui é da empresa. Pressione 1 para saber mais sobre nossas ofertas.',
            'post_call_message' => 'Olá! Obrigado pelo interesse. Nossa equipe entrará em contato em breve.',
            'active' => true,
        ];
    }
}
