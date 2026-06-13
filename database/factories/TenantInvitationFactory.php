<?php

namespace Database\Factories;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TenantInvitation>
 */
class TenantInvitationFactory extends Factory
{
    protected $model = TenantInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invited_by_user_id' => User::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'role' => TenantRole::User,
            'token' => TenantInvitation::hashToken($this->faker->unique()->sha1()),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): self
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }
}
