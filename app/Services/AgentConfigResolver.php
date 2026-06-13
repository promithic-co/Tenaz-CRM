<?php

namespace App\Services;

use App\Models\AgentConfig;
use App\Models\AgentTemplateConfig;
use App\Models\AppSetting;
use App\Models\Lead;
use Illuminate\Support\Facades\Cache;

class AgentConfigResolver
{
    /**
     * Absolute last-resort literals — used when both the template layer AND the
     * config fallback (config/credflow.php) resolve to null/empty (e.g. an operator
     * sets CREDFLOW_AGENT_FALLBACK_MODEL= empty). Guarantees agent_model is NEVER
     * null/empty on ANY resolution path (LLM-02 invariant; closes T-56-05 / WR-01).
     */
    private const LAST_RESORT_PROVIDER = 'openrouter';

    private const LAST_RESORT_MODEL = 'anthropic/claude-haiku-4-5';

    /**
     * @return array<string, mixed>
     */
    public function forLead(Lead $lead): array
    {
        return $this->withFollowUpSettings(
            $this->forAgentId($lead->agent_id, $this->resolveUserIdFromLead($lead)),
            $lead,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forAgentId(?int $agentId, ?int $fallbackUserId = null): array
    {
        if ($agentId !== null) {
            $config = Cache::remember("agent_config_id_{$agentId}", 300, fn () => AgentConfig::query()->where('agent_id', $agentId)->first()?->toArray());

            if ($config !== null) {
                return $this->mergeTemplateLlmDefaults($config);
            }
        }

        return $this->legacyFallback($fallbackUserId);
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyFallback(?int $userId): array
    {
        $legacy = AppSetting::getAgentConfig($userId);
        $extra = AppSetting::getExtraAgentConfig($userId);

        // Cast integer fields
        $extra['followup_first_delay_minutes'] = (int) $extra['followup_first_delay_minutes'];
        $extra['followup_max_count'] = (int) $extra['followup_max_count'];
        $extra['followup_interval_days'] = (int) $extra['followup_interval_days'];
        $extra['followup_persuasion_intensity'] = (int) $extra['followup_persuasion_intensity'];

        $merged = array_merge($extra, $legacy);

        return $this->mergeTemplateLlmDefaults($merged);
    }

    /**
     * The 9 LLM-owned fields that participate in the template waterfall.
     *
     * CAST CONTRACT (WR-02): the waterfall's emptiness check in mergeTemplateLlmDefaults()
     * is `=== null || === ''`, which relies on AgentConfig::toArray() having already cast
     * each field. Every NUMERIC field listed here MUST have a matching entry in
     * AgentConfig::$casts (temperature => float, max_tokens => integer,
     * max_conversation_messages => integer) so a DB `0` arrives as int `0` (kept) — never
     * as the string `"0"` (which would be treated like a real value but is the wrong type),
     * and a numeric NULL never slips through as a string. When adding a numeric field here,
     * add its cast on AgentConfig in the same change (pinned by the "max_tokens = 0 is
     * preserved" test).
     */
    private const LLM_FIELDS = [
        'agent_provider',
        'agent_model',
        'transcription_provider',
        'transcription_model',
        'vision_provider',
        'vision_model',
        'temperature',
        'max_tokens',
        'max_conversation_messages',
    ];

    /**
     * Fetch template LLM defaults from cache (observer-busted, 600s TTL).
     * Uses the SAME cache key as AgentTemplateConfigObserver so updates propagate immediately.
     *
     * @return array<string, mixed>
     */
    private function resolveTemplateLlmConfig(string $slug): array
    {
        return Cache::remember(
            AgentTemplateConfig::cacheKey($slug),
            600,
            fn (): array => AgentTemplateConfig::query()->where('template_slug', $slug)->first()?->toArray() ?? []
        );
    }

    /**
     * Last-resort constant for each LLM field — ensures agent_model is NEVER null on return (LLM-02).
     */
    private function hardDefault(string $field): mixed
    {
        return match ($field) {
            'agent_provider' => config('credflow.agent.fallback_provider') ?: self::LAST_RESORT_PROVIDER,
            'agent_model' => config('credflow.agent.fallback_model') ?: self::LAST_RESORT_MODEL,
            'temperature' => config('credflow.agent.temperature'),
            'max_tokens' => config('credflow.agent.max_tokens'),
            'max_conversation_messages' => config('credflow.agent.max_conversation_messages'),
            'transcription_provider' => 'openai',
            'transcription_model' => 'whisper-1',
            'vision_provider' => 'openai',
            'vision_model' => 'gpt-4o',
            default => null,
        };
    }

    /**
     * Strict three-layer waterfall: client field (non-null/non-empty) → template default → hard-coded constant.
     *
     * CRITICAL: uses `=== null || === ''` — NOT empty() / falsy.
     * empty(0.0) is true, so using empty() would wrongly clobber a legitimate temperature=0.0
     * with the template/last-resort value (Test F, LLM-03).
     *
     * Per-agent cache holds ONLY the client AgentConfig row (unchanged, 300s).
     * Template is merged here at resolve-time from a separately-cached, observer-busted key (600s).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function mergeTemplateLlmDefaults(array $config): array
    {
        $slug = $config['template_slug'] ?? null;
        $template = $slug ? $this->resolveTemplateLlmConfig($slug) : [];

        foreach (self::LLM_FIELDS as $field) {
            $current = $config[$field] ?? null;

            if ($current === null || $current === '') {
                $config[$field] = $template[$field] ?? $this->hardDefault($field);
            }
        }

        return $config;
    }

    private function resolveUserIdFromLead(Lead $lead): ?int
    {
        if ($lead->tenant_id !== null && is_numeric($lead->tenant_id)) {
            return (int) $lead->tenant_id;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function withFollowUpSettings(array $config, Lead $lead): array
    {
        $settings = app(FollowUpSettingsResolver::class)->forLead($lead);

        return array_merge($config, [
            'followup_enabled' => $settings['enabled'],
            'followup_first_delay_minutes' => $settings['first_delay_minutes'],
            'followup_daily_time' => null,
            'followup_max_count' => $settings['max_attempts_within_window'],
            'followup_approach' => $settings['tone'],
            'followup_window_start' => $settings['business_window_start'],
            'followup_window_end' => $settings['business_window_end'],
            'followup_interval_days' => 1,
            'followup_min_interval_minutes' => $settings['min_interval_minutes'],
            'followup_message_type' => $settings['message_type'],
            'followup_tone' => $settings['tone'],
            'followup_persuasion_intensity' => $settings['persuasion_intensity'],
            'followup_custom_instructions' => $settings['custom_instructions'],
        ]);
    }
}
