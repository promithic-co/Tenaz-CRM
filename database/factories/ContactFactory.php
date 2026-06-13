<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ddd = (string) fake()->numberBetween(11, 99);
        $subscriber = '9'.fake()->numerify('########');

        return [
            'tenant_id' => fn () => (string) User::factory()->create()->tenantId,
            'name' => fake()->name(),
            'phone' => '55'.$ddd.$subscriber,
            'email' => fake()->optional()->safeEmail(),
            'cpf' => null,
            'source' => Contact::SOURCE_MANUAL,
            'opt_in_status' => Contact::OPT_PENDING,
            'extra_data' => null,
            'last_seen_at' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(['tenant_id' => $tenantId]);
    }

    public function optedIn(): static
    {
        return $this->state([
            'opt_in_status' => Contact::OPT_IN,
            'opt_in_at' => now(),
        ]);
    }

    public function optedOut(): static
    {
        return $this->state([
            'opt_in_status' => Contact::OPT_OUT,
            'opt_out_at' => now(),
        ]);
    }
}
