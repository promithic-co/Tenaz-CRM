<?php

namespace Database\Factories;

use App\Models\ContactList;
use App\Models\ContactListEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactListEntry>
 */
class ContactListEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_list_id' => ContactList::factory(),
            'phone' => '55'.fake()->numerify('##9########'),
            'name' => fake()->name(),
            'opt_in_status' => 'opted_in',
            'opt_in_at' => now(),
            'opt_out_at' => null,
            'lead_id' => null,
            'extra_data' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'opt_in_status' => 'pending',
            'opt_in_at' => null,
        ]);
    }

    public function optedOut(): static
    {
        return $this->state([
            'opt_in_status' => 'opted_out',
            'opt_out_at' => now(),
        ]);
    }
}
