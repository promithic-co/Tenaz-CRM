<?php

namespace App\Services;

use App\Models\NicheTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AgentTemplateService
{
    /**
     * Return all templates formatted for the frontend gallery, scoped to what
     * $tenantId may see: system templates plus that tenant's private snapshots.
     *
     * DB registry (niche_templates, active rows) is the primary source;
     * config/agent_templates.php remains as fallback while the registry
     * is empty (fresh installs, tests without seeding).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $tenantId = null): array
    {
        $registry = $this->registry()
            ->filter(fn (array $row) => $this->isVisibleTo($row, $tenantId));

        if ($registry->isNotEmpty()) {
            return $registry
                ->map(fn (array $row) => $this->toCard($row))
                ->values()
                ->all();
        }

        return $this->configTemplates()
            ->map(fn (array $tpl, string $slug) => $this->toCard(array_merge($tpl, ['slug' => $slug])))
            ->values()
            ->all();
    }

    /**
     * Find a single template by slug (active registry row first, config fallback).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        $row = $this->registry()->firstWhere('slug', $slug);

        if ($row !== null) {
            return $row;
        }

        return config("agent_templates.templates.{$slug}");
    }

    /**
     * Return only the AgentConfig default field values for the given template slug.
     *
     * @return array<string, mixed>
     */
    public function defaults(string $slug): array
    {
        $template = $this->find($slug);

        return $template['default_config'] ?? $template['defaults'] ?? [];
    }

    /**
     * Return valid template slugs for the given tenant (creation allow-list).
     *
     * Scoped identically to all(): a tenant may only reference system templates
     * or its own private snapshots. This is the validation boundary that stops
     * a forged template_slug from applying another tenant's private snapshot.
     *
     * @return array<int, string>
     */
    public function slugs(?string $tenantId = null): array
    {
        $registry = $this->registry()
            ->filter(fn (array $row) => $this->isVisibleTo($row, $tenantId));

        if ($registry->isNotEmpty()) {
            return $registry->pluck('slug')->all();
        }

        return $this->configTemplates()->keys()->all();
    }

    /**
     * Active registry rows, cached under a single observer-busted key.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function registry(): Collection
    {
        $rows = Cache::remember(
            NicheTemplate::REGISTRY_CACHE_KEY,
            600,
            fn (): array => NicheTemplate::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->toArray()
        );

        return collect($rows);
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function configTemplates(): Collection
    {
        return collect(config('agent_templates.templates', []));
    }

    /**
     * Mirror of NicheTemplate::scopeVisibleTo for the cached in-memory rows:
     * system templates are visible to everyone; private (tenant) snapshots
     * only to their origin tenant. A null tenant sees only system templates.
     *
     * @param  array<string, mixed>  $row
     */
    private function isVisibleTo(array $row, ?string $tenantId): bool
    {
        $visibility = $row['visibility'] ?? 'system';

        if ($visibility === 'system') {
            return true;
        }

        return $tenantId !== null && ($row['origin_tenant_id'] ?? null) === $tenantId;
    }

    /**
     * Normalize a registry row or config entry into the gallery card shape
     * consumed by agentes/Create.vue.
     *
     * @param  array<string, mixed>  $tpl
     * @return array<string, mixed>
     */
    private function toCard(array $tpl): array
    {
        return [
            'slug' => $tpl['slug'],
            'name' => $tpl['name'],
            'label' => $tpl['label'] ?? '',
            'description' => $tpl['description'] ?? '',
            'category' => $tpl['category'] ?? null,
            'tagline' => $tpl['tagline'] ?? '',
            'icon' => $tpl['icon'] ?? 'heart-handshake',
            'mode' => $tpl['mode'] ?? null,
            'use_cases' => $tpl['use_cases'] ?? [],
            'example_first_message' => $tpl['example_first_message'] ?? '',
            'variables_schema' => $tpl['variables_schema'] ?? null,
        ];
    }
}
