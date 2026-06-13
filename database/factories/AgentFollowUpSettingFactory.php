<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentFollowUpSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentFollowUpSetting>
 */
class AgentFollowUpSettingFactory extends Factory
{
    protected $model = AgentFollowUpSetting::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'tenant_id' => fn (array $attrs) => Agent::find($attrs['agent_id'])?->tenant_id ?? 'tenant-'.$this->faker->uuid(),
            'enabled' => true,
            'first_delay_minutes' => 10,
            'min_interval_minutes' => 60,
            'max_attempts_within_window' => 2,
            'business_window_start' => '08:00',
            'business_window_end' => '20:00',
            'timezone' => 'America/Sao_Paulo',
            'message_type' => 'contextual',
            'tone' => 'consultivo',
            'persuasion_intensity' => 2,
            'custom_instructions' => '',
        ];
    }
}
