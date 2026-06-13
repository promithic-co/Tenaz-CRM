<?php

namespace App\Services;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unifies legacy string {@code tenant_id} values (owner user id) with the canonical
 * {@see \App\Models\Tenant} id after {@code tenants} / {@code tenant_user} were introduced.
 */
class LegacyTenantKeyRealignmentService
{
    /**
     * @var list<string>
     */
    private const STRING_TENANT_TABLES = [
        'leads',
        'agents',
        'whatsapp_instances',
        'service_tickets',
        'followup_messages',
        'agent_configs',
        'agent_operational_rules',
        'custom_fields',
        'status_machines',
        'tool_definitions',
        'prompt_templates',
        'prompt_experiments',
        'ai_usage_dailies',
        'campaigns',
        'contact_lists',
        'whatsapp_templates',
        'voice_instances',
        'voice_campaigns',
    ];

    /**
     * @param  (callable(string, string, string): void)|null  $onUserProcessed  ($email, $legacyKey, $canonicalKey)
     * @return array{users_matched: int, rows_updated: int}
     */
    public function realign(?int $onlyUserId = null, bool $dryRun = false, ?callable $onUserProcessed = null): array
    {
        $query = User::query()->orderBy('id');
        if ($onlyUserId !== null) {
            $query->where('id', $onlyUserId);
        }

        $usersMatched = 0;
        $rowsUpdated = 0;

        foreach ($query->cursor() as $user) {
            $primaryTenant = $user->tenants()
                ->wherePivot('role', TenantRole::Owner->value)
                ->orderBy('tenants.id')
                ->first()
                ?? $user->tenants()->orderBy('tenants.id')->first();

            if (! $primaryTenant) {
                continue;
            }

            $legacyKey = (string) $user->id;
            $canonicalKey = (string) $primaryTenant->id;

            if ($legacyKey === $canonicalKey) {
                continue;
            }

            if (! $this->hasLegacyRows($legacyKey)) {
                continue;
            }

            $usersMatched++;
            if ($onUserProcessed !== null) {
                $onUserProcessed($user->email, $legacyKey, $canonicalKey);
            }

            if ($dryRun) {
                $rowsUpdated += $this->countLegacyRows($legacyKey);

                continue;
            }

            DB::transaction(function () use ($legacyKey, $canonicalKey, &$rowsUpdated): void {
                foreach (self::STRING_TENANT_TABLES as $table) {
                    if (! $this->tableExists($table)) {
                        continue;
                    }
                    if ($table === 'ai_usage_dailies') {
                        $rowsUpdated += $this->realignAiUsageDailies($legacyKey, $canonicalKey);
                    } else {
                        $rowsUpdated += DB::table($table)->where('tenant_id', $legacyKey)->update(['tenant_id' => $canonicalKey]);
                    }
                }
            });
        }

        if (! $dryRun && ($usersMatched > 0 || $rowsUpdated > 0)) {
            Log::info('legacy_tenant_keys.realigned', [
                'users_matched' => $usersMatched,
                'rows_updated' => $rowsUpdated,
                'only_user_id' => $onlyUserId,
            ]);
        }

        return ['users_matched' => $usersMatched, 'rows_updated' => $rowsUpdated];
    }

    private function hasLegacyRows(string $legacyKey): bool
    {
        foreach (self::STRING_TENANT_TABLES as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }
            if (DB::table($table)->where('tenant_id', $legacyKey)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function countLegacyRows(string $legacyKey): int
    {
        $n = 0;
        foreach (self::STRING_TENANT_TABLES as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }
            $n += (int) DB::table($table)->where('tenant_id', $legacyKey)->count();
        }

        return $n;
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    private function realignAiUsageDailies(string $legacyKey, string $canonicalKey): int
    {
        $rows = DB::table('ai_usage_dailies')->where('tenant_id', $legacyKey)->get();
        $n = 0;

        foreach ($rows as $row) {
            $exists = DB::table('ai_usage_dailies')
                ->where('date', $row->date)
                ->where('tenant_id', $canonicalKey)
                ->where('agent_id', $row->agent_id)
                ->where('model', $row->model)
                ->exists();

            if ($exists) {
                $target = DB::table('ai_usage_dailies')
                    ->where('date', $row->date)
                    ->where('tenant_id', $canonicalKey)
                    ->where('agent_id', $row->agent_id)
                    ->where('model', $row->model)
                    ->first();

                DB::table('ai_usage_dailies')->where('id', $target->id)->update([
                    'total_requests' => (int) $target->total_requests + (int) $row->total_requests,
                    'total_prompt_tokens' => (int) $target->total_prompt_tokens + (int) $row->total_prompt_tokens,
                    'total_completion_tokens' => (int) $target->total_completion_tokens + (int) $row->total_completion_tokens,
                    'estimated_cost_usd' => (float) $target->estimated_cost_usd + (float) $row->estimated_cost_usd,
                    'updated_at' => now(),
                ]);
                DB::table('ai_usage_dailies')->where('id', $row->id)->delete();
            } else {
                DB::table('ai_usage_dailies')->where('id', $row->id)->update([
                    'tenant_id' => $canonicalKey,
                    'updated_at' => now(),
                ]);
            }
            $n++;
        }

        return $n;
    }
}
