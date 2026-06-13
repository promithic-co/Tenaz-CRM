<?php

namespace Database\Factories;

use App\Enums\TemplateKind;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappTemplate>
 */
class WhatsappTemplateFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'tenant_id' => $user->tenantId,
            'whatsapp_instance_id' => null,
            'kind' => TemplateKind::MetaHsm->value,
            'element_name' => null,
            'meta_template_id' => null,
            'meta_template_name' => null,
            'meta_waba_id' => null,
            'name' => fake()->sentence(3),
            'status' => 'APPROVED',
            'category' => 'MARKETING',
            'language' => 'pt_BR',
            'body' => 'Olá {{1}}, sua proposta de {{2}} está pronta!',
            'variables_count' => 2,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'REJECTED']);
    }

    public function utility(): static
    {
        return $this->state(['category' => 'UTILITY']);
    }

    public function metaHsm(): static
    {
        return $this->state([
            'kind' => TemplateKind::MetaHsm->value,
            'meta_template_id' => fake()->uuid(),
            'meta_template_name' => fake()->slug(2),
            'meta_waba_id' => fake()->uuid(),
        ]);
    }

    public function evolutionPreset(): static
    {
        return $this->state([
            'kind' => TemplateKind::EvolutionPreset->value,
            'element_name' => fake()->unique()->slug(3),
            'meta_template_id' => null,
            'meta_template_name' => null,
            'meta_waba_id' => null,
        ]);
    }
}
