<?php

namespace Database\Factories;

use App\Models\ContactList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactList>
 */
class ContactListFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'tenant_id' => $user->tenantId,
            'name' => fake()->company().' - Lista',
            'description' => null,
            'source' => 'manual',
            'entries_count' => 0,
        ];
    }

    public function fromCsv(): static
    {
        return $this->state(['source' => 'csv_import']);
    }

    public function fromLeads(): static
    {
        return $this->state(['source' => 'lead_filter']);
    }
}
