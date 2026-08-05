<?php

namespace App\Services;

use App\Models\Lead;
use App\Services\WhatsApp\PhoneNumberValidator;
use Illuminate\Support\Facades\Cache;

class PauseService
{
    private const DEFAULT_TTL = 36000; // 10 horas

    /**
     * The pause cache key for a subscriber, keyed on the canonical spelling.
     *
     * A pause is a fact about a person, not about a digit form. Keyed on the raw string,
     * pausing a lead stored with the BR 9th digit left the same subscriber's 12-digit row
     * answering on its own — the operator sees the AI keep talking over a conversation
     * they just took over, which is the worst possible way for this to fail.
     */
    public static function cacheKey(string $tenantId, ?string $phone): string
    {
        $canonical = PhoneNumberValidator::canonical($phone) ?? (string) $phone;

        return "pause:{$tenantId}:{$canonical}";
    }

    public function isPaused(string $phone, string $tenantId = 'default'): bool
    {
        if (Cache::has(self::cacheKey($tenantId, $phone))) {
            return true;
        }

        return Lead::query()
            ->where('tenant_id', $tenantId)
            ->forPhoneVariants($phone)
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
     *
     * Applies to every row for the subscriber, not only the spelling passed in: while
     * duplicates from the 9th-digit split still exist, pausing one and leaving the other
     * live would defeat the takeover.
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
        Cache::put(self::cacheKey($tenantId, $phone), 'paused', $ttlSeconds);

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
            ->forPhoneVariants($phone)
            ->update($updates);
    }

    public function resume(string $phone, string $tenantId = 'default'): void
    {
        Cache::forget(self::cacheKey($tenantId, $phone));

        Lead::query()
            ->where('tenant_id', $tenantId)
            ->forPhoneVariants($phone)
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
            ->forPhoneVariants($phone)
            ->where('followup_status', 'paused')
            ->get();

        foreach ($pausedLeads as $lead) {
            $lead->resumeFollowUp();
        }
    }
}
