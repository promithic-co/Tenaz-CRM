<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\NicheTemplate;
use App\Models\WhatsappInstance;
use App\Services\AgentTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAgentAction
{
    /**
     * Template variables (variables_schema keys) that may customize AgentConfig
     * columns at creation. Keys are variable names, values are config columns.
     * Anything outside this map is silently dropped — user input can never
     * reach provider/model or tenant columns through the wizard.
     */
    private const VARIABLE_COLUMN_MAP = [
        'personality_block' => 'agent_personality',
        'agent_personality' => 'agent_personality',
        'agent_greeting' => 'agent_greeting',
        'extra_rules' => 'extra_rules',
        'required_docs' => 'required_docs',
    ];

    /**
     * Create an agent with optional WhatsApp instance linking.
     *
     * The agent starts inactive. It becomes active only when a free
     * tenant-owned instance is successfully locked and linked (T-60-06).
     * The entire operation runs inside a DB transaction.
     *
     * The niche is an attribute of the chosen template (default_config
     * `agent_niche`, fallback `generic`) — never user input. When the template
     * is a DB registry row, its prompt/tool/status resources are applied to
     * the tenant in the same transaction.
     *
     * When $whatsappInstanceId is supplied but the candidate row is unavailable
     * (race, cross-tenant, or already-assigned), the agent is left inactive and
     * no exception is thrown — the caller decides messaging.
     *
     * @param  array<string, mixed>  $variables  Wizard answers keyed by variables_schema key
     */
    public function execute(
        int $userId,
        string $tenantId,
        string $name,
        string $templateSlug,
        string $companyName,
        ?string $description = null,
        ?int $whatsappInstanceId = null,
        array $variables = [],
    ): Agent {
        return DB::transaction(function () use (
            $userId,
            $tenantId,
            $name,
            $templateSlug,
            $companyName,
            $description,
            $whatsappInstanceId,
            $variables,
        ) {
            $slug = Str::slug($name).'-'.$userId.'-'.Str::lower(Str::random(6));

            $agent = Agent::create([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_active' => false, // remains false until a free instance is proven linked (T-60-06)
                // BelongsToTenant scope already filters by tenant — first agent in tenant becomes default
                'is_default' => ! Agent::query()->exists(),
            ]);

            $templateDefaults = app(AgentTemplateService::class)->defaults($templateSlug);

            AgentConfig::firstOrCreate(
                ['agent_id' => $agent->id],
                array_merge($templateDefaults, $this->configOverrides($variables), [
                    'tenant_id' => $tenantId,
                    'template_slug' => $templateSlug,
                    'agent_name' => $name,
                    'company_name' => $companyName,
                    'agent_niche' => $templateDefaults['agent_niche'] ?? 'generic',
                ])
            );

            NicheTemplate::query()
                ->active()
                ->visibleTo($tenantId)
                ->where('slug', $templateSlug)
                ->first()
                ?->apply($tenantId, $agent->id);

            // Instance link: only when an ID is supplied (D-13).
            // Lock the free candidate inside the transaction to prevent races (T-60-04).
            if ($whatsappInstanceId !== null) {
                $instance = WhatsappInstance::query()
                    ->where('id', $whatsappInstanceId)
                    ->where('user_id', $userId)
                    ->where('tenant_id', $tenantId) // explicit active-tenant scope — raw query bypasses Eloquent global scope
                    ->whereNull('agent_id')
                    ->lockForUpdate()
                    ->first();

                if ($instance) {
                    $instance->update(['agent_id' => $agent->id]);
                    $agent->update(['is_active' => true]); // active ONLY after successful link (T-60-06)
                }
            }

            return $agent;
        });
    }

    /**
     * Map filled wizard variables onto whitelisted AgentConfig columns.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, string>
     */
    private function configOverrides(array $variables): array
    {
        $overrides = [];

        foreach (self::VARIABLE_COLUMN_MAP as $key => $column) {
            $value = trim((string) ($variables[$key] ?? ''));

            if ($value !== '') {
                $overrides[$column] = $value;
            }
        }

        return $overrides;
    }
}
