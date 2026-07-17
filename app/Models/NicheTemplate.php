<?php

namespace App\Models;

use App\Observers\NicheTemplateObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NicheTemplate extends Model
{
    use HasFactory;

    /**
     * Registry cache key for the active template cards (creation gallery).
     * Single driver-agnostic key — production CACHE_STORE=database does not
     * support tag-based invalidation (Pitfall C2). Busted by NicheTemplateObserver.
     */
    public const REGISTRY_CACHE_KEY = 'niche_templates_registry';

    protected $fillable = [
        'slug',
        'name',
        'label',
        'description',
        'category',
        'mode',
        'icon',
        'tagline',
        'use_cases',
        'example_first_message',
        'prompt_templates',
        'tool_definitions',
        'status_machine',
        'custom_fields',
        'default_config',
        'variables_schema',
        'niche_sections',
        'agent_class',
        'visibility',
        'origin_tenant_id',
        'is_active',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::observe(NicheTemplateObserver::class);
    }

    protected function casts(): array
    {
        return [
            'use_cases' => 'array',
            'prompt_templates' => 'array',
            'tool_definitions' => 'array',
            'status_machine' => 'array',
            'custom_fields' => 'array',
            'default_config' => 'array',
            'variables_schema' => 'array',
            'niche_sections' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Active templates eligible for the agent-creation gallery. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Restrict to templates a tenant may see/use: system-wide templates plus
     * that tenant's own private (visibility=tenant) snapshots. A null tenant
     * gets only system templates — never another tenant's private rows.
     *
     * This is the cross-tenant boundary for the marketplace (Slice 6): private
     * snapshots carry the source tenant's prompt/tool copies and must never
     * leak into another tenant's gallery, slug allow-list, or apply path.
     */
    public function scopeVisibleTo(Builder $query, ?string $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId): void {
            $q->where('visibility', 'system')
                ->orWhere(function (Builder $inner) use ($tenantId): void {
                    $inner->where('visibility', 'tenant')
                        ->where('origin_tenant_id', $tenantId);

                    if ($tenantId === null) {
                        $inner->whereRaw('1 = 0');
                    }
                });
        });
    }

    /**
     * Apply this niche template to a tenant, creating all resources.
     */
    public function apply(string $tenantId, ?int $agentId = null): void
    {
        $this->applyPromptTemplates($tenantId, $agentId);
        $this->applyToolDefinitions($tenantId, $agentId);
        $this->applyStatusMachine($tenantId);
        $this->applyCustomFields($tenantId);

        Log::info('NicheTemplate applied', [
            'slug' => $this->slug,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
        ]);
    }

    private function applyPromptTemplates(string $tenantId, ?int $agentId): void
    {
        foreach ($this->prompt_templates ?? [] as $tpl) {
            PromptTemplate::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'agent_id' => $agentId,
                    'slug' => $tpl['slug'],
                    'is_active' => true,
                ],
                [
                    'name' => $tpl['name'],
                    'type' => $tpl['type'],
                    'content' => $tpl['content'],
                    'variables_schema' => $tpl['variables_schema'] ?? null,
                ]
            );
        }
    }

    private function applyToolDefinitions(string $tenantId, ?int $agentId): void
    {
        foreach ($this->tool_definitions ?? [] as $def) {
            ToolDefinition::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'agent_id' => $agentId,
                    'slug' => $def['slug'],
                ],
                [
                    'name' => $def['name'],
                    'description' => $def['description'] ?? '',
                    'type' => $def['type'] ?? 'webhook',
                    'config' => $def['config'] ?? null,
                    'schema' => $def['schema'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Replace the tenant's status machine — guarded against orphaning leads.
     * If production leads sit on statuses the incoming machine does not define,
     * swapping the machine would strand them (no transitions out, broken panel
     * filters), so the apply is rejected listing the statuses still in use.
     *
     * @throws ValidationException
     */
    private function applyStatusMachine(string $tenantId): void
    {
        if (empty($this->status_machine)) {
            return;
        }

        $newSlugs = array_column($this->status_machine['statuses'] ?? [], 'slug');

        $strandedStatuses = Lead::query()
            ->where('tenant_id', $tenantId)
            ->production()
            ->whereNotIn('status', $newSlugs)
            ->distinct()
            ->pluck('status');

        if ($strandedStatuses->isNotEmpty()) {
            throw ValidationException::withMessages([
                'template_slug' => 'Este modelo não pode ser aplicado: existem conversas ativas nos status '
                    .$strandedStatuses->implode(', ')
                    .', que não existem no novo funil.',
            ]);
        }

        StatusMachine::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'entity_type' => 'lead',
                'statuses' => $this->status_machine['statuses'] ?? [],
                'transitions' => $this->status_machine['transitions'] ?? [],
                'initial_status' => $this->status_machine['initial_status'] ?? 'novo',
            ]
        );
    }

    private function applyCustomFields(string $tenantId): void
    {
        foreach ($this->custom_fields ?? [] as $i => $field) {
            CustomField::updateOrCreate(
                ['tenant_id' => $tenantId, 'entity_type' => 'lead', 'slug' => $field['slug']],
                [
                    'label' => $field['label'],
                    'type' => $field['type'] ?? 'text',
                    'options' => $field['options'] ?? null,
                    'is_required' => $field['is_required'] ?? false,
                    'sort_order' => $field['sort_order'] ?? $i,
                ]
            );
        }
    }
}
