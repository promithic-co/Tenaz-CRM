<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'user_id', 'tenant_id'];

    /**
     * Get a setting value, scoped to a user.
     * Falls back to global (user_id = null) if no user-specific value exists.
     * Uses Laravel Cache for cross-worker consistency.
     */
    public static function get(string $key, mixed $default = null, ?int $userId = null): mixed
    {
        $cacheKey = "app_setting:{$key}:".($userId ?? 'global');

        return Cache::remember($cacheKey, 300, function () use ($key, $userId, $default) {
            $query = static::where('key', $key)->where('user_id', $userId)->first();

            // Fall back to global if user-specific not found
            if ($query === null && $userId !== null) {
                $query = static::where('key', $key)->whereNull('user_id')->first();
            }

            return $query?->value ?? $default;
        });
    }

    /** Upsert a setting value, scoped to a user. */
    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['key' => $key, 'user_id' => $userId],
            ['value' => $value]
        );

        Cache::forget("app_setting:{$key}:".($userId ?? 'global'));
    }

    /**
     * Get a setting value scoped to a tenant (multi-tenant path).
     * Falls back to global (tenant_id = null) if no tenant-specific value exists.
     */
    public static function getForTenant(string $tenantId, string $key, mixed $default = null): mixed
    {
        $cacheKey = "app_setting_tenant:{$tenantId}:{$key}";

        return Cache::remember($cacheKey, 300, function () use ($tenantId, $key, $default) {
            $row = static::where('key', $key)
                ->where('tenant_id', $tenantId)
                ->whereNull('user_id')
                ->first();

            if ($row === null) {
                $row = static::where('key', $key)
                    ->whereNull('tenant_id')
                    ->whereNull('user_id')
                    ->first();
            }

            return $row?->value ?? $default;
        });
    }

    /** Upsert a setting value scoped to a tenant. */
    public static function setForTenant(string $tenantId, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key, 'tenant_id' => $tenantId, 'user_id' => null],
            ['value' => $value]
        );

        Cache::forget("app_setting_tenant:{$tenantId}:{$key}");
    }

    /** Agent config keys that require cross-process cache invalidation. */
    private const AGENT_CONFIG_KEYS = [
        'agent_name', 'company_name', 'agent_personality', 'max_chars',
        'agent_greeting', 'required_docs', 'extra_rules',
        'agent_provider', 'agent_model',
        'temperature', 'max_tokens', 'max_conversation_messages',
    ];

    /** Default values for the legacy agent config fallback. */
    private const AGENT_CONFIG_DEFAULTS = [
        'agent_name' => 'Tenaz CRM',
        'company_name' => 'Amec',
        'agent_personality' => 'direta, acolhedora e profissional',
        'max_chars' => '300',
        'agent_greeting' => 'Cumprimente o cliente pelo nome de forma breve e natural, apresente-se apenas pelo seu nome e diga que está aqui para ajudar com crédito. Sem pitch ou lista de benefícios.',
        'required_docs' => 'RG/CNH, comprovante de residência, dados bancários (banco/agência/conta)',
        'extra_rules' => '',
        'agent_provider' => 'openai',
        'agent_model' => 'gpt-4o-mini',
        'temperature' => '0.4',
        'max_tokens' => '1024',
        'max_conversation_messages' => '24',
    ];

    /** Extra legacy agent config keys resolved alongside the primary settings. */
    private const EXTRA_AGENT_CONFIG_KEYS = [
        'followup_first_delay_minutes', 'followup_daily_time', 'followup_max_count',
        'followup_approach', 'followup_window_start', 'followup_window_end',
        'followup_interval_days', 'followup_message_type', 'followup_tone',
        'followup_persuasion_intensity', 'followup_custom_instructions',
        'transcription_provider', 'transcription_model',
        'vision_provider', 'vision_model',
    ];

    /** Default values for the extra legacy agent config fallback. */
    private const EXTRA_AGENT_CONFIG_DEFAULTS = [
        'followup_first_delay_minutes' => '10',
        'followup_daily_time' => '10:00',
        'followup_max_count' => '4',
        'followup_approach' => 'natural',
        'followup_window_start' => '08:00',
        'followup_window_end' => '20:00',
        'followup_interval_days' => '1',
        'followup_message_type' => 'reengajamento',
        'followup_tone' => 'consultivo',
        'followup_persuasion_intensity' => '2',
        'followup_custom_instructions' => '',
        'transcription_provider' => 'openai',
        'transcription_model' => 'whisper-1',
        'vision_provider' => 'openai',
        'vision_model' => 'gpt-4o',
    ];

    /** Automatically invalidate caches when any setting is persisted. */
    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            Cache::forget("app_setting:{$setting->key}:".($setting->user_id ?? 'global'));
            if ($setting->tenant_id !== null) {
                Cache::forget("app_setting_tenant:{$setting->tenant_id}:{$setting->key}");
            }
            if (in_array($setting->key, self::AGENT_CONFIG_KEYS, true)) {
                self::invalidateAgentConfigCache($setting->user_id);
            }
        });
    }

    public static function defaults(): array
    {
        return [
            'followup_first_delay_minutes' => '10',
            'followup_daily_time' => '10:00',
            'followup_max_count' => '4',
            'followup_approach' => 'natural',
            'agent_provider' => 'openai',
            'agent_model' => 'gpt-4o-mini',
            'transcription_provider' => 'openai',
            'transcription_model' => 'whisper-1',
            'vision_provider' => 'openai',
            'vision_model' => 'gpt-4o',
            'media_max_audio_mb' => '10',
            'media_max_image_mb' => '5',
            'media_max_document_mb' => '10',
        ];
    }

    public static function seedDefaults(?int $userId = null): void
    {
        foreach (static::defaults() as $key => $value) {
            static::firstOrCreate(
                ['key' => $key, 'user_id' => $userId],
                ['value' => $value]
            );
        }
    }

    /** Cache key prefix for cross-process agent config (invalidated on save). */
    private const AGENT_CONFIG_CACHE_KEY = 'credflow:agent_config:';

    /** Cache key prefix for extra legacy agent config. */
    private const EXTRA_AGENT_CONFIG_CACHE_KEY = 'credflow:extra_config:';

    /**
     * Load all agent-related settings in a single query, returning an associative array.
     * Uses Laravel Cache (database/redis) so config changes propagate to queue workers.
     *
     * @return array{agent_name: string, company_name: string, agent_personality: string, max_chars: string, agent_greeting: string, required_docs: string, extra_rules: string, agent_provider: string, agent_model: string, temperature: string, max_tokens: string, max_conversation_messages: string}
     */
    public static function getAgentConfig(?int $userId = null): array
    {
        $cacheKey = self::AGENT_CONFIG_CACHE_KEY.($userId ?? 'global');

        return self::loadKeySet(self::AGENT_CONFIG_KEYS, self::AGENT_CONFIG_DEFAULTS, $userId, $cacheKey, 3600);
    }

    /**
     * Load extra legacy agent settings in a single query.
     *
     * @return array<string, string>
     */
    public static function getExtraAgentConfig(?int $userId = null): array
    {
        $cacheKey = self::EXTRA_AGENT_CONFIG_CACHE_KEY.($userId ?? 'global');

        return self::loadKeySet(self::EXTRA_AGENT_CONFIG_KEYS, self::EXTRA_AGENT_CONFIG_DEFAULTS, $userId, $cacheKey, 300);
    }

    /**
     * Load a cached setting key set with user-specific, global, then default precedence.
     *
     * @param  list<string>  $keys
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    private static function loadKeySet(array $keys, array $defaults, ?int $userId, string $cacheKey, int $ttl): array
    {
        return Cache::remember($cacheKey, $ttl, function () use ($keys, $defaults, $userId): array {
            $rows = static::whereIn('key', $keys)
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                    if ($userId !== null) {
                        $q->orWhereNull('user_id');
                    }
                })
                ->get(['key', 'value', 'user_id']);

            $userValues = [];
            $globalValues = [];
            foreach ($rows as $row) {
                if ($row->user_id === $userId && $userId !== null) {
                    $userValues[$row->key] = $row->value;
                } elseif ($row->user_id === null) {
                    $globalValues[$row->key] = $row->value;
                }
            }

            $result = [];
            foreach ($keys as $k) {
                $result[$k] = $userValues[$k] ?? $globalValues[$k] ?? $defaults[$k];
            }

            return $result;
        });
    }

    /** Invalidate agent config cache for a user (call after saving config). */
    public static function invalidateAgentConfigCache(?int $userId = null): void
    {
        Cache::forget(self::AGENT_CONFIG_CACHE_KEY.($userId ?? 'global'));
    }

    /**
     * Flush caches (useful in tests).
     * Clears all Laravel Cache entries for settings.
     */
    public static function flushCache(): void
    {
        Cache::flush();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
