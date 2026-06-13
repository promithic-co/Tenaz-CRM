<?php

namespace App\Services;

class AgentTemplateService
{
    /**
     * Return all templates formatted for the frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $templates = config('agent_templates.templates', []);

        return array_values(array_map(
            fn (string $slug, array $tpl) => [
                'slug' => $slug,
                'name' => $tpl['name'],
                'label' => $tpl['label'],
                'description' => $tpl['description'],
                'tagline' => $tpl['tagline'],
                'icon' => $tpl['icon'],
                'mode' => $tpl['mode'] ?? null,
                'use_cases' => $tpl['use_cases'] ?? [],
                'example_first_message' => $tpl['example_first_message'],
            ],
            array_keys($templates),
            $templates,
        ));
    }

    /**
     * Find a single template by slug.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
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

        return $template['defaults'] ?? [];
    }

    /**
     * Return all valid template slugs (used for validation rules).
     *
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return array_keys(config('agent_templates.templates', []));
    }
}
