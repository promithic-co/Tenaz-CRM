<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @extends Factory<Tag>
 *
 * Tags are tenant-scoped. Callers MUST chain `->forTenant($tenantId)` before
 * calling `create()` / `make()` — without it the factory would silently spin
 * up a User just to mint a tenant_id (slow, hides cross-test side effects).
 *
 * Usage:
 *   Tag::factory()->forTenant($tenantId)->create();
 *   Tag::factory()->forTenant($tenantId)->hot()->create();
 */
class TagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(asText: true);

        return [
            'tenant_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'color' => fake()->randomElement(Tag::COLORS),
            'is_hot' => false,
            'created_by' => null,
            'usage_count' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Tag $tag): void {
            if ($tag->getAttribute('tenant_id') === null || $tag->getAttribute('tenant_id') === '') {
                throw new RuntimeException(
                    'TagFactory requires ->forTenant($tenantId). Tags are tenant-scoped.',
                );
            }
        });
    }

    /**
     * Mark the tag as a "hot" (strong signal) tag.
     */
    public function hot(): static
    {
        return $this->state(['is_hot' => true]);
    }

    /**
     * Mark the tag as AI-detectable with a default description and confidence.
     */
    public function aiDetectable(float $minConfidence = 0.70, string $description = 'AI detectable tag for testing'): static
    {
        return $this->state([
            'ai_detectable' => true,
            'ai_description' => $description,
            'ai_min_confidence' => $minConfidence,
        ]);
    }

    /**
     * Internal fields (tenant_id, usage_count, created_by) are not mass-assignable
     * on the Tag model. The factory bypasses that intentionally via forceFill so
     * tests can spin up tagged tenants without going through the controller.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Tag
    {
        $tag = new Tag;
        $tag->forceFill($attributes);

        return $tag;
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(['tenant_id' => $tenantId]);
    }
}
