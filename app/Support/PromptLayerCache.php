<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Per-tenant versioned cache for the agent prompt layer (SCALE-8). Webhook tool definitions
 * and prompt templates/experiments are immutable between operator edits, yet were read from
 * the DB on every AI turn. Reads go through a tenant-versioned key; any write to a
 * ToolDefinition / PromptTemplate / PromptExperiment bumps that tenant's version, which
 * invalidates every prompt-layer key for the tenant at once — without enumerating the
 * (agent, type, slug) fan-out (and the null-agent scope that would otherwise fan out to all
 * agents). The 300s TTL is the backstop, matching the existing agent_config_id_ cache.
 */
class PromptLayerCache
{
    private const TTL = 300;

    /** Sentinel stored for a null result: Cache::get() treats a stored null as a miss. */
    private const EMPTY = '__none__';

    public static function version(string $tenantId): int
    {
        return (int) Cache::get(self::versionKey($tenantId), 0);
    }

    /**
     * Invalidate the whole prompt layer for a tenant by advancing its version. Read-modify-write
     * is not atomic, but a lost increment only risks a stale read bounded by the 300s TTL, and
     * edits are low-frequency single-operator actions — so contention is negligible.
     */
    public static function bump(string $tenantId): void
    {
        Cache::put(self::versionKey($tenantId), self::version($tenantId) + 1, now()->addDays(30));
    }

    /**
     * Remember a query result under the tenant-versioned key. A null result (no active
     * experiment, no matching template) is stored via a sentinel so the no-match case — the
     * common one — does not re-query on every turn.
     *
     * @template TValue
     *
     * @param  callable():TValue  $resolver
     * @return TValue
     */
    public static function remember(string $tenantId, string $suffix, callable $resolver): mixed
    {
        $key = "prompt_layer:{$tenantId}:v".self::version($tenantId).":{$suffix}";

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached === self::EMPTY ? null : $cached;
        }

        $value = $resolver();
        Cache::put($key, $value ?? self::EMPTY, self::TTL);

        return $value;
    }

    private static function versionKey(string $tenantId): string
    {
        return "prompt_layer_ver:{$tenantId}";
    }
}
