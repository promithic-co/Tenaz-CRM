<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactListEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignMessage>
 */
class CampaignMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'contact_list_entry_id' => ContactListEntry::factory(),
            'provider_message_id' => null,
            'status' => 'pending',
            'error_code' => null,
            'error_message' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
            'failed_at' => null,
            'template_params_resolved' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state([
            'status' => 'sent',
            'provider_message_id' => fake()->uuid(),
            'sent_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => 'delivered',
            'provider_message_id' => fake()->uuid(),
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_code' => '1001',
            'error_message' => 'User not opted in',
            'failed_at' => now(),
        ]);
    }
}
