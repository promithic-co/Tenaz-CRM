<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory()->create();
        $tenantId = $user->tenantId;

        return [
            'tenant_id' => $tenantId,
            'whatsapp_instance_id' => WhatsappInstance::factory()->state(['user_id' => $user->id, 'tenant_id' => $tenantId]),
            'contact_list_id' => fn (array $attributes) => ContactList::factory()
                ->state(['tenant_id' => $attributes['tenant_id']]),
            'whatsapp_template_id' => fn (array $attributes) => WhatsappTemplate::factory()
                ->state(['tenant_id' => $attributes['tenant_id']]),
            'name' => fake()->sentence(3),
            'status' => 'draft',
            'template_params_mapping' => null,
            'daily_limit' => 1000,
            'delay_between_ms' => 1000,
            'error_threshold_percent' => 10,
            'total_recipients' => 0,
            'total_sent' => 0,
            'total_delivered' => 0,
            'total_read' => 0,
            'total_failed' => 0,
        ];
    }

    public function scheduled(): static
    {
        return $this->state([
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
        ]);
    }

    public function sending(): static
    {
        return $this->state([
            'status' => 'sending',
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state([
            'status' => 'paused',
            'started_at' => now()->subHour(),
            'paused_at' => now(),
        ]);
    }
}
