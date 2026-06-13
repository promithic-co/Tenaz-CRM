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

    public function pause(string $phone, string $tenantId = 'default', int $ttlSeconds = self::DEFAULT_TTL): void
    {
        Cache::put("pause:{$tenantId}:{$phone}", 'paused', $ttlSeconds);

        Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('whatsapp', $phone)
            ->update([
                'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
                'ai_paused_until' => now()->addSeconds($ttlSeconds),
                'ai_paused_reason' => 'human_takeover',
            ]);
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
