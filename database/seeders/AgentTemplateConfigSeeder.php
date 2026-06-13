<?php

namespace Database\Seeders;

use App\Models\AgentTemplateConfig;
use Illuminate\Database\Seeder;

class AgentTemplateConfigSeeder extends Seeder
{
    /**
     * Idempotently seed one row per template slug from config/agent_templates.php.
     *
     * Safe to run in production — uses updateOrCreate keyed on template_slug
     * so repeated runs keep the row count stable (Pitfall C1 deploy-order rule).
     */
    public function run(): void
    {
        $templates = config('agent_templates.templates', []);

        foreach (array_keys($templates) as $slug) {
            $template = $templates[$slug];

            $temperature = $template['defaults']['temperature']
                ?? config('credflow.agent.temperature', 0.4);

            AgentTemplateConfig::updateOrCreate(
                ['template_slug' => $slug],
                [
                    'agent_provider' => 'openrouter',
                    'agent_model' => 'anthropic/claude-haiku-4-5',
                    'transcription_provider' => 'openai',
                    'transcription_model' => 'whisper-1',
                    'vision_provider' => 'openai',
                    'vision_model' => 'gpt-4o',
                    'temperature' => $temperature,
                    'max_tokens' => config('credflow.agent.max_tokens', 1024),
                    'max_conversation_messages' => config('credflow.agent.max_conversation_messages', 24),
                ]
            );
        }
    }
}
