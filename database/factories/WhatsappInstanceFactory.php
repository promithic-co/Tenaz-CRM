<?php

namespace Database\Factories;

use App\Enums\WhatsAppProvider;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappInstance>
 */
class WhatsappInstanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => fn (array $attr) => User::find($attr['user_id'])?->tenantId ?? (string) $attr['user_id'],
            'agent_id' => null,
            'default_ai_mode' => 'automatic',
            'provider' => WhatsAppProvider::MetaCloud,
            'name' => 'instance-'.$this->faker->unique()->lexify('????'),
            'display_name' => $this->faker->optional()->company(),
            'api_url' => 'https://graph.facebook.com',
            'api_key' => $this->faker->sha256(),
            'meta_phone_number_id' => (string) $this->faker->numerify('###############'),
            'meta_waba_id' => (string) $this->faker->numerify('###############'),
            'meta_access_token' => 'test-token-'.$this->faker->sha256(),
        ];
    }

    /** Meta Cloud API — per-instance credentials stored in DB. */
    public function metaCloud(): static
    {
        return $this->state(fn (): array => [
            'provider' => WhatsAppProvider::MetaCloud,
            'api_url' => 'https://graph.facebook.com',
            'meta_phone_number_id' => (string) $this->faker->numerify('###############'),
            'meta_waba_id' => (string) $this->faker->numerify('###############'),
            'meta_access_token' => 'test-token-'.$this->faker->sha256(),
            'meta_token_permanent' => true,
            'meta_token_expires_at' => null,
            'meta_quality_rating' => 'GREEN',
            // api_key is not used by Meta Cloud (auth is per meta_access_token) but column is NOT NULL
            'api_key' => '',
        ]);
    }
}
