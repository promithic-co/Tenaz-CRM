<?php

namespace App\Services;

use App\Models\AgentConfig;
use App\Models\AgentFollowUpSetting;
use App\Models\FollowUpSetting;
use App\Models\Lead;
use Illuminate\Support\Facades\Cache;

class FollowUpSettingsResolver
{
    /**
     * Resolution chain (first hit wins):
     *   1. agent_followup_settings row (per agent / per WABA)
     *   2. followup_settings row       (tenant defaults)
     *   3. agent_configs legacy row    (pre-tenant table)
     *   4. hardcoded defaults
     */

    /**
     * @return array<string, mixed>
     */
    public function forLead(Lead $lead): array
    {
        if ($lead->agent_id !== null) {
            return $this->forAgent((int) $lead->agent_id, (string) $lead->tenant_id);
        }

        return $this->forTenant((string) $lead->tenant_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function forAgent(int $agentId, string $tenantId): array
    {
        $cacheKey = "followup_settings:agent:{$agentId}";

        $row = Cache::remember($cacheKey, 300, function () use ($agentId): array {
            $row = AgentFollowUpSetting::withoutGlobalScope('tenant')
                ->where('agent_id', $agentId)
                ->first();

            return $row?->toArray() ?? [];
        });

        if ($row !== []) {
            return $this->normalize($row);
        }

        return $this->forTenant($tenantId, $agentId);
    }

    /**
     * @return array<string, mixed>
     */
    public function forTenant(string $tenantId, ?int $agentId = null): array
    {
        $tenantCacheKey = "followup_settings:tenant:{$tenantId}";

        $settings = Cache::remember($tenantCacheKey, 300, function () use ($tenantId): array {
            $settings = FollowUpSetting::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->first();

            return $settings?->toArray() ?? [];
        });

        if ($settings !== []) {
            return $this->normalize($settings);
        }

        $legacyCacheKey = $agentId === null
            ? "followup_settings:tenant:{$tenantId}:legacy"
            : "followup_settings:tenant:{$tenantId}:agent:{$agentId}:legacy";

        return Cache::remember($legacyCacheKey, 300, fn (): array => $this->normalize($this->legacyDefaults($tenantId, $agentId)));
    }

    public function forget(string $tenantId, ?int $agentId = null): void
    {
        Cache::forget("followup_settings:tenant:{$tenantId}");
        Cache::forget("followup_settings:tenant:{$tenantId}:legacy");

        if ($agentId !== null) {
            Cache::forget("followup_settings:agent:{$agentId}");
            Cache::forget("followup_settings:tenant:{$tenantId}:agent:{$agentId}:legacy");

            return;
        }

        // Sweep per-agent caches (both new and legacy) for this tenant when no agentId given.
        $agentIds = AgentConfig::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->pluck('agent_id')
            ->filter()
            ->unique()
            ->all();

        $extra = AgentFollowUpSetting::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->pluck('agent_id')
            ->all();

        foreach (array_unique([...$agentIds, ...$extra]) as $id) {
            Cache::forget("followup_settings:agent:{$id}");
            Cache::forget("followup_settings:tenant:{$tenantId}:agent:{$id}:legacy");
        }
    }

    public function forgetAgent(int $agentId): void
    {
        Cache::forget("followup_settings:agent:{$agentId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'enabled' => true,
            'first_delay_minutes' => 10,
            'min_interval_minutes' => 60,
            'max_attempts_within_window' => 2,
            'business_window_start' => '08:00',
            'business_window_end' => '20:00',
            'timezone' => 'America/Sao_Paulo',
            'message_type' => 'contextual',
            'tone' => 'consultivo',
            'persuasion_intensity' => 2,
            'custom_instructions' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyDefaults(string $tenantId, ?int $agentId): array
    {
        $query = AgentConfig::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId);

        if ($agentId !== null) {
            $query->orderByRaw('agent_id = ? desc', [$agentId]);
        }

        $config = $query->orderBy('id')->first();

        if (! $config) {
            return $this->defaults();
        }

        return [
            ...$this->defaults(),
            'first_delay_minutes' => $config->followup_first_delay_minutes,
            'max_attempts_within_window' => $config->followup_max_count,
            'business_window_start' => $config->followup_window_start ?? '08:00',
            'business_window_end' => $config->followup_window_end ?? '20:00',
            'message_type' => $config->followup_message_type ?? 'contextual',
            'tone' => $config->followup_tone ?? 'consultivo',
            'persuasion_intensity' => $config->followup_persuasion_intensity ?? 2,
            'custom_instructions' => $config->followup_custom_instructions ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalize(array $settings): array
    {
        $defaults = $this->defaults();
        $settings = array_merge($defaults, $settings);

        $settings['enabled'] = filter_var($settings['enabled'], FILTER_VALIDATE_BOOLEAN);
        $settings['first_delay_minutes'] = max(1, (int) $settings['first_delay_minutes']);
        $settings['min_interval_minutes'] = max(5, (int) $settings['min_interval_minutes']);
        $settings['max_attempts_within_window'] = max(1, (int) $settings['max_attempts_within_window']);
        $settings['business_window_start'] = substr((string) $settings['business_window_start'], 0, 5);
        $settings['business_window_end'] = substr((string) $settings['business_window_end'], 0, 5);
        $settings['timezone'] = (string) ($settings['timezone'] ?: $defaults['timezone']);
        $settings['message_type'] = (string) ($settings['message_type'] ?: $defaults['message_type']);
        $settings['tone'] = (string) ($settings['tone'] ?: $defaults['tone']);
        $settings['persuasion_intensity'] = min(5, max(1, (int) $settings['persuasion_intensity']));
        $settings['custom_instructions'] = (string) ($settings['custom_instructions'] ?? '');

        return $settings;
    }
}
