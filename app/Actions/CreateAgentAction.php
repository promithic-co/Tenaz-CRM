<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\AgentFollowUpSetting;
use App\Models\NicheTemplate;
use App\Models\WhatsappInstance;
use App\Services\AgentTemplateService;
use App\Services\FollowUpSettingsResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAgentAction
{
    public function __construct(private readonly FollowUpSettingsResolver $followUpSettingsResolver) {}

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
     * @param  array<string, mixed>  $followUp  Independent follow-up settings from the wizard
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
        array $followUp = [],
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
            $followUp,
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

            AgentFollowUpSetting::create($this->followUpPayload($agent, $tenantId, $followUp));

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

    /**
     * @param  array<string, mixed>  $followUp
     * @return array<string, mixed>
     */
    private function followUpPayload(Agent $agent, string $tenantId, array $followUp): array
    {
        $settings = array_merge($this->followUpSettingsResolver->defaults(), $followUp);

        return [
            'agent_id' => $agent->id,
            'tenant_id' => $tenantId,
            'enabled' => (bool) $settings['enabled'],
            'first_delay_minutes' => (int) $settings['first_delay_minutes'],
            'min_interval_minutes' => (int) $settings['min_interval_minutes'],
            'max_attempts_within_window' => (int) ($settings['max_count'] ?? $settings['max_attempts_within_window']),
            'business_window_start' => (string) ($settings['followup_window_start'] ?? $settings['business_window_start']),
            'business_window_end' => (string) ($settings['followup_window_end'] ?? $settings['business_window_end']),
            'timezone' => (string) $settings['timezone'],
            'message_type' => (string) $settings['message_type'],
            'tone' => (string) $settings['tone'],
            'persuasion_intensity' => (int) $settings['persuasion_intensity'],
            'custom_instructions' => (string) $settings['custom_instructions'],
        ];
    }
}
