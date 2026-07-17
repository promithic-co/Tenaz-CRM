<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\NicheTemplate;
use App\Models\PromptTemplate;
use App\Models\ToolDefinition;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a working agent into a replicable NicheTemplate (marketplace snapshot).
 *
 * It copies the agent's *configuration shape* — prompt templates, webhook tool
 * shapes, personality/greeting/rules — and strips everything tenant-specific so
 * the result can be applied to a fresh company by filling only a few variables.
 *
 * Security invariants (Slice 6 — snapshots cross tenants):
 *   - Never copies lead data. Only AgentConfig / PromptTemplate / ToolDefinition
 *     rows are read; no Lead, Contact, or conversation content is touched.
 *   - Never copies secrets. Webhook URLs collapse to the {{WEBHOOK_URL}}
 *     placeholder and request headers (auth tokens) are dropped, so applying
 *     the template forces the new tenant to re-enter its own endpoint.
 *   - Never leaks the origin's identity. The literal agent_name / company_name
 *     values are replaced by {{agent_name}} / {{company_name}} placeholders in
 *     every prompt body before storage.
 */
class SnapshotAgentAsTemplateAction
{
    /**
     * Config keys copied into the template default_config. Deliberately excludes
     * agent_name / company_name (tenant identity) and provider/model (platform
     * managed via AgentTemplateConfig, not per-snapshot).
     *
     * @var list<string>
     */
    private const COPIED_CONFIG_KEYS = [
        'agent_niche',
        'max_chars',
        'temperature',
        'agent_personality',
        'agent_greeting',
        'required_docs',
        'extra_rules',
    ];

    /**
     * Tool config keys that may carry credentials or endpoints; dropped or
     * replaced during sanitization so no secret ever lands in a template.
     *
     * @var list<string>
     */
    private const TOOL_SECRET_KEYS = ['headers', 'token', 'secret', 'api_key', 'auth', 'bearer'];

    /**
     * @param  'system'|'tenant'  $visibility
     *
     * @throws RuntimeException When the agent has no configuration to snapshot.
     */
    public function execute(Agent $agent, string $name, string $visibility = 'tenant'): NicheTemplate
    {
        $config = AgentConfig::query()->where('agent_id', $agent->id)->first();

        if ($config === null) {
            throw new RuntimeException('Agente sem configuração para gerar modelo.');
        }

        $tenantId = (string) $agent->tenant_id;
        $identityReplacements = $this->identityReplacements($config, (string) $agent->name);

        return NicheTemplate::create([
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'name' => $name,
            'label' => $name,
            'description' => $this->sanitizeText('Modelo criado a partir do agente '.$agent->name.'.', $identityReplacements),
            'category' => 'personalizado',
            'mode' => 'receptivo',
            'icon' => 'sparkles',
            'tagline' => $this->sanitizeText((string) ($config->agent_personality ?? ''), $identityReplacements),
            'use_cases' => [],
            'example_first_message' => $this->sanitizeText((string) ($config->agent_greeting ?? ''), $identityReplacements),
            'prompt_templates' => $this->snapshotPromptTemplates($tenantId, $agent->id, $identityReplacements),
            'tool_definitions' => $this->snapshotToolDefinitions($tenantId, $agent->id, $identityReplacements),
            'status_machine' => null,
            'custom_fields' => [],
            'default_config' => $this->snapshotConfig($config, $identityReplacements),
            'variables_schema' => $this->buildVariablesSchema($config, $identityReplacements),
            'niche_sections' => [],
            'agent_class' => null,
            'visibility' => $visibility === 'system' ? 'system' : 'tenant',
            'origin_tenant_id' => $tenantId,
            'is_active' => true,
            'sort_order' => 100,
        ]);
    }

    /**
     * Literal → placeholder map for scrubbing the origin tenant's identity out
     * of every copied text field. Longer strings first so a company_name that
     * contains the agent_name is replaced before the shorter token.
     *
     * @return array<string, string>
     */
    private function identityReplacements(AgentConfig $config, string $agentLabel = ''): array
    {
        $map = [];

        foreach (['company_name' => '{{company_name}}', 'agent_name' => '{{agent_name}}'] as $column => $placeholder) {
            $value = trim((string) ($config->{$column} ?? ''));
            if ($value !== '') {
                $map[$value] = $placeholder;
            }
        }

        $agentLabel = trim($agentLabel);
        if ($agentLabel !== '' && ! isset($map[$agentLabel])) {
            $map[$agentLabel] = '{{agent_name}}';
        }

        uksort($map, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $map;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function sanitizeText(string $text, array $replacements): string
    {
        if ($replacements === []) {
            return $text;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array<int, array<string, mixed>>
     */
    private function snapshotPromptTemplates(string $tenantId, int $agentId, array $replacements): array
    {
        return PromptTemplate::query()
            ->forTenant($tenantId)
            ->where('agent_id', $agentId)
            ->active()
            ->get()
            ->map(fn (PromptTemplate $tpl): array => [
                'slug' => $tpl->slug,
                'name' => $tpl->name,
                'type' => $tpl->type,
                'content' => $this->sanitizeText((string) $tpl->content, $replacements),
                'variables_schema' => $tpl->variables_schema,
            ])
            ->all();
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array<int, array<string, mixed>>
     */
    private function snapshotToolDefinitions(string $tenantId, int $agentId, array $replacements): array
    {
        return ToolDefinition::query()
            ->forTenant($tenantId)
            ->where('agent_id', $agentId)
            ->active()
            ->get()
            ->map(fn (ToolDefinition $tool): array => [
                'slug' => $tool->slug,
                'name' => $tool->name,
                'description' => $this->sanitizeText((string) ($tool->description ?? ''), $replacements),
                'type' => $tool->type,
                'config' => $this->sanitizeToolConfig($tool->config ?? []),
                'schema' => $tool->schema,
            ])
            ->all();
    }

    /**
     * Strip credentials and collapse the endpoint to a placeholder the applying
     * tenant must fill in. Never emit the origin's real URL, headers, or tokens.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function sanitizeToolConfig(array $config): array
    {
        foreach (self::TOOL_SECRET_KEYS as $secretKey) {
            unset($config[$secretKey]);
        }

        if (array_key_exists('url', $config)) {
            $config['url'] = '{{WEBHOOK_URL}}';
        }

        return $config;
    }

    /**
     * Copy the reusable config fields, scrubbing the origin's identity out of
     * any string value (agent_greeting / extra_rules may name the company or
     * agent) so a promoted snapshot never carries it into another tenant.
     *
     * @param  array<string, string>  $replacements
     * @return array<string, mixed>
     */
    private function snapshotConfig(AgentConfig $config, array $replacements): array
    {
        $snapshot = [];

        foreach (self::COPIED_CONFIG_KEYS as $key) {
            $value = $config->{$key} ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $snapshot[$key] = is_string($value)
                ? $this->sanitizeText($value, $replacements)
                : $value;
        }

        return $snapshot;
    }

    /**
     * The wizard fields a replicating tenant fills. Always agent_name and
     * company_name; personality/greeting are offered pre-filled from the
     * origin's (identity-scrubbed) values so the clone starts usable.
     *
     * @param  array<string, string>  $replacements
     * @return array<int, array<string, mixed>>
     */
    private function buildVariablesSchema(AgentConfig $config, array $replacements): array
    {
        return [
            ['key' => 'agent_name', 'label' => 'Nome do agente', 'type' => 'text', 'required' => true, 'max' => 100],
            ['key' => 'company_name', 'label' => 'Nome da empresa', 'type' => 'text', 'required' => true, 'max' => 100],
            ['key' => 'personality_block', 'label' => 'Personalidade', 'type' => 'textarea', 'required' => false, 'max' => 1000, 'placeholder' => $this->sanitizeText((string) ($config->agent_personality ?? ''), $replacements)],
            ['key' => 'agent_greeting', 'label' => 'Saudação inicial', 'type' => 'textarea', 'required' => false, 'max' => 300],
        ];
    }
}
