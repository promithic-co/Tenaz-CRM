<?php

namespace App\Services;

use App\Events\ConversationSessionClosed;
use App\Events\ConversationSessionOpened;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owns the ConversationSession (atendimento) lifecycle: opening a session on the
 * first inbound / re-engagement, and closing it when the atendimento concludes.
 *
 * The single-open-per-lead invariant is enforced by the partial unique index; this
 * service serialises opens behind a cache lock to avoid the exception path, and
 * still catches the unique violation as a fallback when the lock degrades.
 */
class ConversationSessionLifecycleService
{
    /** A returning inbound after this many idle days opens a fresh session. */
    public const REENGAGEMENT_GAP_DAYS = 30;

    /** Open sessions idle longer than this are auto-closed by the scheduler. */
    public const AUTO_CLOSE_INACTIVITY_DAYS = 45;

    /** Lead statuses that end the sales cycle — a new inbound is a re-engagement. */
    public const TERMINAL_STATUSES = ['convertido', 'desqualificado', 'optou_sair', 'sem_credito'];

    /**
     * Return the lead's open session, creating one when none is open. Idempotent
     * and concurrency-safe: the cache lock serialises racing inbound redeliveries,
     * and the partial unique index is the durable guarantee.
     *
     * @param  array<string, mixed>  $metadata  merged into a newly created session only
     *                                          (never overwrites an already-open session)
     */
    public function ensureOpenSession(Lead $lead, ?string $reason = null, array $metadata = []): ConversationSession
    {
        $lockKey = "session_open_{$lead->tenant_id}_{$lead->id}";

        try {
            return Cache::lock($lockKey, 8)->block(5, fn (): ConversationSession => $this->openGuarded($lead, $reason, $metadata));
        } catch (LockTimeoutException) {
            // Lock contention: the winner has (or is about to) open the session. Fall
            // back to a lock-free attempt; the unique index still prevents a double open.
            return $this->openGuarded($lead, $reason, $metadata);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function openGuarded(Lead $lead, ?string $reason, array $metadata = []): ConversationSession
    {
        return DB::transaction(function () use ($lead, $reason, $metadata): ConversationSession {
            $open = ConversationSession::withoutGlobalScopes()
                ->where('lead_id', $lead->id)
                ->where('status', ConversationSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                return $open;
            }

            $resolvedReason = $this->resolveOpenReason($lead, $reason);
            $number = (int) ConversationSession::withoutGlobalScopes()
                ->where('lead_id', $lead->id)
                ->max('number') + 1;

            try {
                $session = ConversationSession::create([
                    'tenant_id' => (string) $lead->tenant_id,
                    'lead_id' => $lead->id,
                    'number' => $number,
                    'status' => ConversationSession::STATUS_OPEN,
                    'open_reason' => $resolvedReason,
                    'opened_at' => now(),
                    'last_message_at' => now(),
                    'metadata' => $metadata === [] ? null : $metadata,
                ]);
            } catch (QueryException $e) {
                // A concurrent open won the race despite the lock (Redis lock degraded);
                // the partial unique index rejected this one. Resolve to the winner.
                $existing = ConversationSession::withoutGlobalScopes()
                    ->where('lead_id', $lead->id)
                    ->where('status', ConversationSession::STATUS_OPEN)
                    ->first();

                if ($existing === null) {
                    throw $e;
                }

                return $existing;
            }

            try {
                event(new ConversationSessionOpened(
                    tenantId: (string) $session->tenant_id,
                    leadId: $lead->id,
                    sessionId: $session->id,
                    number: $session->number,
                    openReason: $session->open_reason,
                ));
            } catch (\Throwable) {
            }

            $this->refreshDashboardMetrics((string) $session->tenant_id);

            return $session;
        });
    }

    /**
     * A returning inbound after a terminal status must not be answered by the AI:
     * park the lead in the human queue and pause automation so an operator picks it
     * up. Called only for a freshly opened post-terminal re-engagement session.
     */
    public function applyPostTerminalGuard(Lead $lead): void
    {
        $lead->update([
            'operational_stage' => Lead::STAGE_HUMAN_PENDING,
            'ai_paused_until' => now()->addHours(10),
            'ai_paused_reason' => 'post_terminal_reengagement',
        ]);
    }

    public function close(ConversationSession $session, string $outcome, ?User $user = null): ConversationSession
    {
        if ($session->status === ConversationSession::STATUS_CLOSED) {
            return $session;
        }

        $session->forceFill([
            'status' => ConversationSession::STATUS_CLOSED,
            'outcome' => $outcome,
            'closed_at' => now(),
            'metadata' => array_merge($session->metadata ?? [], array_filter([
                'closed_by' => $user?->id,
            ])),
        ])->save();

        try {
            event(new ConversationSessionClosed(
                tenantId: (string) $session->tenant_id,
                leadId: $session->lead_id,
                sessionId: $session->id,
                outcome: $outcome,
            ));
        } catch (\Throwable) {
        }

        $this->refreshDashboardMetrics((string) $session->tenant_id);

        return $session;
    }

    /**
     * Enqueue a debounced KPI recompute so the atendimento counters (opened today,
     * reengaged, closed, outcomes, avg time-to-close) stay live. Off the hot path via
     * the debounce gate; never lets a metrics failure break session open/close.
     */
    private function refreshDashboardMetrics(string $tenantId): void
    {
        try {
            app(DashboardMetricsService::class)->dispatchUpdate($tenantId);
        } catch (\Throwable) {
        }
    }

    /**
     * Close the lead's open session, if any, with the given outcome. No-op when the
     * lead has no open session (idempotent for repeated terminal transitions).
     */
    public function closeOpenForLead(Lead $lead, string $outcome, ?User $user = null): ?ConversationSession
    {
        $open = ConversationSession::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('status', ConversationSession::STATUS_OPEN)
            ->first();

        if ($open === null) {
            return null;
        }

        return $this->close($open, $outcome, $user);
    }

    /**
     * Map a lead's terminal status to a session outcome. Returns null for
     * non-terminal statuses.
     */
    public function outcomeForStatus(?string $status): ?string
    {
        return match ($status) {
            'convertido' => ConversationSession::OUTCOME_CONVERTED,
            'desqualificado', 'optou_sair', 'sem_credito' => ConversationSession::OUTCOME_LOST,
            default => null,
        };
    }

    private function resolveOpenReason(Lead $lead, ?string $requested): string
    {
        // An explicit campaign/manual reason from the caller wins.
        if ($requested !== null && $requested !== ConversationSession::OPEN_REASON_FIRST_CONTACT) {
            return $requested;
        }

        $last = ConversationSession::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->orderByDesc('number')
            ->first();

        if ($last === null) {
            return ConversationSession::OPEN_REASON_FIRST_CONTACT;
        }

        if (in_array((string) $lead->status, self::TERMINAL_STATUSES, true)) {
            return ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL;
        }

        return ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_INACTIVITY;
    }
}
