<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AgentTemplateConfig>
 */
class AgentTemplateConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_slug' => $this->faker->unique()->slug(2),
            'agent_provider' => 'openrouter',
            'agent_model' => 'anthropic/claude-haiku-4-5',
            'transcription_provider' => 'openai',
            'transcription_model' => 'whisper-1',
            'vision_provider' => 'openai',
            'vision_model' => 'gpt-4o',
            'temperature' => 0.4,
            'max_tokens' => 1024,
            'max_conversation_messages' => 24,
        ];
    }
}
