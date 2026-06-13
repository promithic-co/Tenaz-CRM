<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'onboarded_at' => now(),
            'onboarding_whatsapp_skipped_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $tenant = \App\Models\Tenant::create(['name' => $user->name]);
            $user->tenants()->attach($tenant->id, ['role' => \App\Enums\TenantRole::Owner->value]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user is a platform super-admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['is_super_admin' => true]);
    }

    /**
     * Indicate that the user has not yet completed onboarding.
     * All three onboarding flow fields are null so wizard gate tests are explicit.
     */
    public function notOnboarded(): static
    {
        return $this->state(fn (array $a) => [
            'onboarded_at' => null,
            'onboarding_agent_id' => null,
            'onboarding_whatsapp_skipped_at' => null,
        ]);
    }
}
