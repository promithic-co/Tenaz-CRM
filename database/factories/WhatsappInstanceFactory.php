<?php

namespace Database\Factories;

use App\Enums\MetaHealthStatus;
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
            // Healthy by default so existing suites are not gated by the campaign
            // health check; use the degraded states below to exercise it.
            'meta_health_status' => MetaHealthStatus::Available,
            'meta_health_entities' => [],
            'meta_health_checked_at' => now(),
        ];
    }

    /** Meta reports the number as unable to send at all. */
    public function healthBlocked(string $reason = 'Your business account is restricted.'): static
    {
        return $this->state(fn (): array => [
            'meta_health_status' => MetaHealthStatus::Blocked,
            'meta_health_entities' => [
                [
                    'type' => 'WABA',
                    'id' => (string) $this->faker->numerify('###############'),
                    'status' => MetaHealthStatus::Blocked->value,
                    'reasons' => [$reason],
                ],
            ],
            'meta_health_checked_at' => now(),
        ]);
    }

    /** Meta allows sending, but under a restriction (e.g. display name pending). */
    public function healthLimited(string $reason = 'Your display name has not been approved yet.'): static
    {
        return $this->state(fn (): array => [
            'meta_health_status' => MetaHealthStatus::Limited,
            'meta_health_entities' => [
                [
                    'type' => 'PHONE_NUMBER',
                    'id' => (string) $this->faker->numerify('###############'),
                    'status' => MetaHealthStatus::Limited->value,
                    'reasons' => [$reason],
                ],
            ],
            'meta_health_checked_at' => now(),
        ]);
    }

    /** The health probe has never run, or failed. Must never gate a send. */
    public function healthUnknown(): static
    {
        return $this->state(fn (): array => [
            'meta_health_status' => MetaHealthStatus::Unknown,
            'meta_health_entities' => [],
            'meta_health_checked_at' => null,
        ]);
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
