<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Services\AgentTemplateService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AgentConfig>
 */
class AgentConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'tenant_id' => fn (array $attr) => Agent::find($attr['agent_id'])?->tenant_id ?? (string) $attr['agent_id'],
            'agent_niche' => 'inss',
            'template_slug' => null,
            'agent_name' => 'Tenaz CRM',
            'company_name' => 'Amec',
            'agent_personality' => 'direta, acolhedora e profissional',
            'max_chars' => 300,
            'agent_greeting' => 'Cumprimente pelo nome e apresente-se como consultora da empresa',
            'required_docs' => 'RG/CNH, comprovante de residência, dados bancários (banco/agência/conta)',
            'extra_rules' => '',
            'agent_provider' => 'openai',
            'agent_model' => 'gpt-4o-mini',
            'transcription_provider' => 'openai',
            'transcription_model' => 'whisper-1',
            'vision_provider' => 'openai',
            'vision_model' => 'gpt-4o',
            'escalation_whatsapp_number' => '',
            'temperature' => 0.4,
            'max_tokens' => 1024,
            'max_conversation_messages' => 24,
            'followup_first_delay_minutes' => 10,
            'followup_daily_time' => '10:00',
            'followup_max_count' => 4,
            'followup_approach' => 'natural',
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
            'followup_interval_days' => 1,
            'followup_message_type' => 'reengajamento',
            'followup_tone' => 'consultivo',
            'followup_persuasion_intensity' => 2,
            'followup_custom_instructions' => '',
        ];
    }

    public function siape(): static
    {
        return $this->state(fn () => ['agent_niche' => 'siape']);
    }

    public function clt(): static
    {
        return $this->state(fn () => ['agent_niche' => 'clt']);
    }

    public function aliciaReceptivo(): static
    {
        $defaults = app(AgentTemplateService::class)->defaults('alicia-receptivo');

        return $this->state(fn () => array_merge($defaults, ['template_slug' => 'alicia-receptivo']));
    }

    public function ariaBulk(): static
    {
        $defaults = app(AgentTemplateService::class)->defaults('aria-bulk');

        return $this->state(fn () => array_merge($defaults, ['template_slug' => 'aria-bulk']));
    }
}
