<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Cache;

class PauseService
{
    private const DEFAULT_TTL = 36000; // 10 horas

    public function isPaused(string $phone, string $tenantId = 'default'): bool
    {
        if (Cache::has("pause:{$tenantId}:{$phone}")) {
            return true;
        }

        return Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('whatsapp', $phone)
            ->whereNotNull('ai_paused_until')
            ->where('ai_paused_until', '>', now())
            ->exists();
    }

    /**
     * Pause AI for a lead: write the pause cache key and the lead pause fields.
     *
     * Canonical pause primitive — handoff/transfer callers pass stage/reason/pausedBy
     * to record their specific takeover context instead of hand-rolling the same
     * cache + lead update. `pausedBy` and `followupStatus` are written only when
     * provided, so generic two-argument callers keep their original behavior.
     */
    public function pause(
        string $phone,
        string $tenantId = 'default',
        int $ttlSeconds = self::DEFAULT_TTL,
        string $stage = Lead::STAGE_HUMAN_ACTIVE,
        string $reason = 'human_takeover',
        ?int $pausedBy = null,
        ?string $followupStatus = null,
    ): void {
        Cache::put("pause:{$tenantId}:{$phone}", 'paused', $ttlSeconds);

        $updates = [
            'operational_stage' => $stage,
            'ai_paused_until' => now()->addSeconds($ttlSeconds),
            'ai_paused_reason' => $reason,
        ];

        if ($pausedBy !== null) {
            $updates['ai_paused_by'] = $pausedBy;
        }

        if ($followupStatus !== null) {
            $updates['followup_status'] = $followupStatus;
        }

        Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('whatsapp', $phone)
            ->update($updates);
    }

    public function resume(string $phone, string $tenantId = 'default'): void
    {
        Cache::forget("pause:{$tenantId}:{$phone}");

        Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('whatsapp', $phone)
            ->update([
                'ai_paused_until' => null,
                'ai_paused_reason' => null,
                'ai_paused_by' => null,
            ]);

        // Flip any leads whose follow-up was paused by the human takeover back to active
        // (subject to customer-service window). Without this, paused leads stay paused
        // forever after pause expires/resumes.
        $pausedLeads = Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('whatsapp', $phone)
            ->where('followup_status', 'paused')
            ->get();

        foreach ($pausedLeads as $lead) {
            $lead->resumeFollowUp();
        }
    }
}
