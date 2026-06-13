<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Agente '.fake()->unique()->word();

        return [
            'user_id' => User::factory(),
            'tenant_id' => fn (array $attr) => User::find($attr['user_id'])?->tenantId ?? (string) $attr['user_id'],
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(10, 9999),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'is_default' => false,
        ];
    }
}
