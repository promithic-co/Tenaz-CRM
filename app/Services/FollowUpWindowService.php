<?php

namespace App\Services;

use App\Models\FollowupMessage;
use App\Models\Lead;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class FollowUpWindowService
{
    public const CUSTOMER_SERVICE_WINDOW_HOURS = 24;

    public const FREE_ENTRY_POINT_WINDOW_HOURS = 72;

    private const TERMINAL_STATUSES = [
        'optou_sair',
        'convertido',
        'escalado',
        'desqualificado',
    ];

    public function windowClosesAt(Lead $lead): ?CarbonInterface
    {
        if ($lead->service_window_expires_at !== null) {
            return $lead->service_window_expires_at;
        }

        if ($lead->last_inbound_at === null) {
            return null;
        }

        return $lead->last_inbound_at->copy()->addHours(self::CUSTOMER_SERVICE_WINDOW_HOURS);
    }

    public function isInsideCustomerServiceWindow(Lead $lead, ?CarbonInterface $now = null): bool
    {
        $closesAt = $this->windowClosesAt($lead);

        if ($closesAt === null) {
            return false;
        }

        $now ??= now();

        return $now->lessThanOrEqualTo($closesAt);
    }

    public function remainingMinutes(Lead $lead, ?CarbonInterface $now = null): int
    {
        $closesAt = $this->windowClosesAt($lead);

        if ($closesAt === null) {
            return 0;
        }

        $now ??= now();

        return max(0, (int) floor($now->diffInMinutes($closesAt, false)));
    }

    public function freeEntryPointClosesAt(Lead $lead): ?CarbonInterface
    {
        return $lead->free_entry_point_expires_at;
    }

    public function isInsideFreeEntryPointWindow(Lead $lead, ?CarbonInterface $now = null): bool
    {
        $closesAt = $this->freeEntryPointClosesAt($lead);

        if ($closesAt === null) {
            return false;
        }

        $now ??= now();

        return $now->lessThanOrEqualTo($closesAt);
    }

    public function canSendFreeFormMessage(Lead $lead, ?CarbonInterface $now = null): bool
    {
        return $this->isInsideCustomerServiceWindow($lead, $now)
            || $this->isInsideFreeEntryPointWindow($lead, $now);
    }

    public function freeFormWindowClosesAt(Lead $lead, ?CarbonInterface $now = null): ?CarbonInterface
    {
        $now ??= now();

        $openWindows = array_filter([
            $this->isInsideCustomerServiceWindow($lead, $now) ? $this->windowClosesAt($lead) : null,
            $this->isInsideFreeEntryPointWindow($lead, $now) ? $this->freeEntryPointClosesAt($lead) : null,
        ]);

        if ($openWindows !== []) {
            return collect($openWindows)->sortBy(fn (CarbonInterface $date): int => $date->getTimestamp())->last();
        }

        $knownWindows = array_filter([
            $this->windowClosesAt($lead),
            $this->freeEntryPointClosesAt($lead),
        ]);

        if ($knownWindows === []) {
            return null;
        }

        return collect($knownWindows)->sortBy(fn (CarbonInterface $date): int => $date->getTimestamp())->last();
    }

    public function freeFormRemainingMinutes(Lead $lead, ?CarbonInterface $now = null): int
    {
        $closesAt = $this->freeFormWindowClosesAt($lead, $now);

        if ($closesAt === null) {
            return 0;
        }

        $now ??= now();

        return max(0, (int) floor($now->diffInMinutes($closesAt, false)));
    }

    /**
     * `$effectiveAiMode` is the pre-resolved mode (raw ai_mode with the instance
     * default_ai_mode fallback — see ConversationAutomationService::resolveInstanceDefaultedModes);
     * when null, the raw `$lead->ai_mode` column is used, preserving legacy behavior.
     *
     * @param  array<string, mixed>  $settings
     * @return array{eligible: bool, reason: string, due_at: ?string, window_expires_at: ?string, remaining_minutes: int}
     */
    public function evaluate(Lead $lead, array $settings, ?CarbonInterface $now = null, ?PauseService $pause = null, ?string $effectiveAiMode = null): array
    {
        $now ??= now();
        $windowClosesAt = $this->freeFormWindowClosesAt($lead, $now);
        $remainingMinutes = $this->freeFormRemainingMinutes($lead, $now);

        $base = [
            'eligible' => false,
            'reason' => 'unknown',
            'due_at' => null,
            'window_expires_at' => $windowClosesAt?->toIso8601String(),
            'remaining_minutes' => $remainingMinutes,
        ];

        if (! (bool) ($settings['enabled'] ?? true)) {
            return [...$base, 'reason' => 'disabled'];
        }

        if ($lead->is_sandbox) {
            return [...$base, 'reason' => 'sandbox'];
        }

        if ($lead->followup_status !== 'active') {
            return [...$base, 'reason' => 'not_active'];
        }

        $aiMode = $effectiveAiMode ?? $lead->ai_mode;

        if ($aiMode === Lead::AI_MODE_MANUAL || in_array($lead->operational_stage, Lead::HUMAN_HANDOFF_STAGES, true)) {
            return [...$base, 'reason' => 'human_paused'];
        }

        if ($lead->isAiPaused()) {
            return [...$base, 'reason' => 'human_paused'];
        }

        if (in_array((string) $lead->status, self::TERMINAL_STATUSES, true)) {
            return [...$base, 'reason' => 'terminal_status'];
        }

        if ($pause && $pause->isPaused((string) $lead->whatsapp, (string) $lead->tenant_id)) {
            return [...$base, 'reason' => 'human_paused'];
        }

        if ($lead->last_inbound_at === null && $lead->free_entry_point_expires_at === null) {
            return [...$base, 'reason' => 'no_inbound_window'];
        }

        if (! $this->canSendFreeFormMessage($lead, $now)) {
            return [...$base, 'reason' => 'window_expired_requires_hsm'];
        }

        if (! $this->isInsideBusinessHours($settings, $now)) {
            return [...$base, 'reason' => 'outside_business_hours'];
        }

        $maxAttempts = (int) ($settings['max_attempts_within_window'] ?? 2);
        if ((int) $lead->followup_count >= $maxAttempts) {
            return [...$base, 'reason' => 'max_reached'];
        }

        $dueAt = $this->nextDueAt($lead, $settings);
        $dueAtIso = $dueAt?->toIso8601String();

        if ($dueAt !== null && $now->lessThan($dueAt)) {
            return [
                ...$base,
                'reason' => (int) $lead->followup_count === 0 ? 'first_delay_not_reached' : 'interval_not_reached',
                'due_at' => $dueAtIso,
            ];
        }

        return [
            ...$base,
            'eligible' => true,
            'reason' => 'eligible',
            'due_at' => $dueAtIso,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function nextDueAt(Lead $lead, array $settings): ?CarbonInterface
    {
        // Anchor on the last inbound message; a free-entry-point lead that never
        // wrote (F7) anchors on the FEP opening so first_delay/min_interval still
        // apply instead of firing immediately.
        $anchor = $lead->last_inbound_at ?? $lead->free_entry_point_started_at;

        if ($anchor === null) {
            return null;
        }

        $minInterval = (int) ($settings['min_interval_minutes'] ?? 60);

        // Any prior followupMessages row (success or no_reply) sets a backoff floor —
        // prevents tight re-fire loop when the agent returns empty/sentinel replies.
        $lastSentAt = FollowupMessage::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->latest('sent_at')
            ->value('sent_at');

        if ((int) $lead->followup_count === 0) {
            $firstDue = $anchor->copy()->addMinutes((int) ($settings['first_delay_minutes'] ?? 10));

            if ($lastSentAt !== null) {
                $backoffDue = Carbon::parse($lastSentAt)->addMinutes($minInterval);

                return $backoffDue->greaterThan($firstDue) ? $backoffDue : $firstDue;
            }

            return $firstDue;
        }

        if ($lastSentAt === null) {
            $lastSentAt = $lead->last_interaction_at ?? $anchor;
        }

        return Carbon::parse($lastSentAt)->addMinutes($minInterval);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function isInsideBusinessHours(array $settings, ?CarbonInterface $now = null): bool
    {
        $timezone = (string) ($settings['timezone'] ?? 'America/Sao_Paulo');
        $now = ($now ?? now())->copy()->setTimezone($timezone);
        $windowStart = substr((string) ($settings['business_window_start'] ?? '08:00'), 0, 5);
        $windowEnd = substr((string) ($settings['business_window_end'] ?? '20:00'), 0, 5);

        $start = Carbon::createFromFormat('H:i', $windowStart, $timezone)
            ->setDate($now->year, $now->month, $now->day);
        $end = Carbon::createFromFormat('H:i', $windowEnd, $timezone)
            ->setDate($now->year, $now->month, $now->day);

        // Overnight window (e.g., 22:00 → 06:00): end is on the next day. Allow when
        // current time is >= start OR <= end.
        if ($end->lessThan($start)) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThanOrEqualTo($end);
        }

        return $now->greaterThanOrEqualTo($start) && $now->lessThanOrEqualTo($end);
    }
}
