<?php

namespace App\Models;

use App\Support\PromptLayerCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PromptExperiment extends Model
{
    protected static function booted(): void
    {
        static::saved(fn (self $experiment) => PromptLayerCache::bump((string) $experiment->tenant_id));
        static::deleted(fn (self $experiment) => PromptLayerCache::bump((string) $experiment->tenant_id));
    }

    protected $fillable = [
        'tenant_id',
        'slug',
        'name',
        'prompt_type',
        'variants',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('prompt_type', $type);
    }

    /**
     * Assign a variant to a lead using weighted selection.
     * Once assigned, the lead retains the same variant (sticky).
     */
    public function assignVariant(Lead $lead): ?string
    {
        if ($lead->experiment_slug === $this->slug && $lead->experiment_variant !== null) {
            return $lead->experiment_variant;
        }

        $variant = $this->selectWeightedVariant($lead->id);

        $lead->update([
            'experiment_slug' => $this->slug,
            'experiment_variant' => $variant,
        ]);

        return $variant;
    }

    /**
     * Deterministic weighted variant selection based on lead ID.
     */
    public function selectWeightedVariant(int $leadId): string
    {
        $variants = collect($this->variants);
        $totalWeight = $variants->sum('weight');

        $position = $leadId % max($totalWeight, 1);
        $cumulative = 0;

        foreach ($variants as $variant) {
            $cumulative += ($variant['weight'] ?? 1);
            if ($position < $cumulative) {
                return $variant['slug'];
            }
        }

        return $variants->first()['slug'];
    }

    /**
     * Get the template slug for a given variant slug.
     */
    public function getTemplateSlug(string $variantSlug): ?string
    {
        $variant = collect($this->variants)->firstWhere('slug', $variantSlug);

        return $variant['template_slug'] ?? null;
    }

    /**
     * Aggregate conversion results per variant.
     *
     * @return Collection<string, array{assigned: int, converted: int, rate: float}>
     */
    public function results(): Collection
    {
        // Already scoped to the experiment's own tenant — bypass the ambient
        // tenant global scope so aggregation does not depend on the acting
        // user's active tenant (which may differ in reports/CLI contexts).
        $leads = Lead::withoutGlobalScope('tenant')
            ->where('experiment_slug', $this->slug)
            ->where('tenant_id', $this->tenant_id)
            ->selectRaw('experiment_variant, count(*) as assigned')
            ->selectRaw("sum(case when status in ('convertido','fechado') then 1 else 0 end) as converted")
            ->groupBy('experiment_variant')
            ->get();

        return $leads->mapWithKeys(function ($row) {
            $rate = $row->assigned > 0
                ? round((int) $row->converted / (int) $row->assigned * 100, 1)
                : 0.0;

            return [$row->experiment_variant => [
                'assigned' => (int) $row->assigned,
                'converted' => (int) $row->converted,
                'rate' => $rate,
            ]];
        });
    }
}
