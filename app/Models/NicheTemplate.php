<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class NicheTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'prompt_templates',
        'tool_definitions',
        'status_machine',
        'custom_fields',
        'default_config',
    ];

    protected function casts(): array
    {
        return [
            'prompt_templates' => 'array',
            'tool_definitions' => 'array',
            'status_machine' => 'array',
            'custom_fields' => 'array',
            'default_config' => 'array',
        ];
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

    private function applyStatusMachine(string $tenantId): void
    {
        if (empty($this->status_machine)) {
            return;
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
