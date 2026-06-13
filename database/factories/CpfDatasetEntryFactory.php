<?php

namespace Database\Factories;

use App\Models\CpfDataset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CpfDatasetEntry>
 */
class CpfDatasetEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cpf = str_pad(fake()->numerify('###########'), 11, '0', STR_PAD_LEFT);

        return [
            'cpf_dataset_id' => CpfDataset::factory(),
            'cpf' => $cpf,
            'nome' => fake()->name(),
            'status_expected' => fake()->randomElement(['QUALIFICADO', 'SEM_CREDITO', 'DESQUALIFICADO']),
            'qualified_json' => null,
            'promosys_raw' => null,
        ];
    }
}
