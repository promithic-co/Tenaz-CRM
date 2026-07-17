<?php

namespace Database\Factories;

use App\Models\NicheTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NicheTemplate>
 */
class NicheTemplateFactory extends Factory
{
    protected $model = NicheTemplate::class;

    public function definition(): array
    {
        $name = fake()->firstName();

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => $name,
            'label' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'category' => 'generico',
            'mode' => 'receptivo',
            'icon' => 'heart-handshake',
            'tagline' => fake()->words(3, true),
            'use_cases' => ['atendimento'],
            'example_first_message' => 'Em que posso te ajudar hoje?',
            'default_config' => [
                'agent_name' => $name,
                'max_chars' => 320,
                'temperature' => 0.6,
            ],
            'variables_schema' => [
                ['key' => 'agent_name', 'label' => 'Nome do agente', 'type' => 'text', 'required' => true, 'max' => 100],
                ['key' => 'company_name', 'label' => 'Nome da empresa', 'type' => 'text', 'required' => true, 'max' => 100],
            ],
            'visibility' => 'system',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
