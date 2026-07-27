<?php

namespace Database\Factories;

use App\Models\CustomField;
use App\Services\CustomFieldService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    protected $model = CustomField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = ucfirst($this->faker->unique()->word());

        return [
            'tenant_id' => (string) $this->faker->numberBetween(1, 9),
            'entity_type' => CustomFieldService::ENTITY_LEAD,
            'slug' => Str::slug($label, '_'),
            'label' => $label,
            'type' => 'text',
            'options' => null,
            'is_required' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    public function select(array $options): static
    {
        return $this->state(fn (): array => [
            'type' => 'select',
            'options' => $options,
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
