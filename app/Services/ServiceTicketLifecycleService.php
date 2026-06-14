<?php

namespace App\Services;

use App\Events\AtendimentoCountersUpdated;
use App\Events\HumanHandoffClaimed;
use App\Events\HumanHandoffResolved;
use App\Events\HumanHandoffReturnedToAi;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceTicketLifecycleService
{
    public function __construct(private readonly PauseService $pause) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createOpenTicket(Lead $lead, string $type, array $data = [], bool $pauseAi = true): ServiceTicket
    {
        return DB::transaction(function () use ($lead, $type, $data, $pauseAi): ServiceTicket {
            $priority = $this->normalizePriority((string) Arr::get($data, 'priority', $this->inferPriority($type, $data)));

            $ticket = ServiceTicket::query()
                ->where('lead_id', $lead->id)
                ->where('type', $type)
                ->active()
                ->latest()
                ->lockForUpdate()
                ->first();

            $payload = [
                'tenant_id' => (string) $lead->tenant_id,
                'lead_id' => $lead->id,
                'type' => $type,
                'status' => ServiceTicket::STATUS_OPEN,
                'priority' => $priority,
                'sla_due_at' => Arr::get($data, 'sla_due_at') ?? ServiceTicket::slaForPriority($priority),
                'reason' => Arr::get($data, 'reason'),
                'summary' => Arr::get($data, 'summary'),
                'credit_available' => Arr::get($data, 'credit_available'),
                'chosen_product' => Arr::get($data, 'chosen_product'),
                'total_value' => Arr::get($data, 'total_value'),
                'installment_value' => Arr::get($data, 'installment_value'),
                'observations' => Arr::get($data, 'observations'),
                'metadata' => Arr::get($data, 'metadata'),
            ];

            if ($ticket) {
                $ticket->fill(array_filter($payload, fn ($value): bool => $value !== null));
                $ticket->save();
            } else {
                $ticket = ServiceTicket::create($payload);
            }

            if ($pauseAi) {
                $this->pause->pause((string) $lead->whatsapp, (string) $lead->tenant_id);
                $lead->update([
                    'operational_stage' => Lead::STAGE_HUMAN_PENDING,
                    'ai_paused_reason' => 'ticket_created',
                ]);
            } elseif ($type === 'no_credit') {
                $lead->update(['operational_stage' => Lead::STAGE_FUTURE_OPPORTUNITY]);
            }

            return $ticket->fresh(['lead', 'assignedUser']);
        });
    }

    /**
     * Claim a ticket by locking fresh rows for both ticket and lead.
     * Rejects already-claimed tickets (idempotent for same user).
     */
    public function claim(ServiceTicket $ticket, User $user): ServiceTicket
    {
        $result = DB::transaction(function () use ($ticket, $user): ServiceTicket {
            // Reload and lock fresh ticket row — route-bound model may be stale.
            $lockedTicket = ServiceTicket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedTicket->status, ServiceTicket::CLAIMABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'ticket' => 'Este atendimento não pode ser assumido.',
                ]);
            }

            // Idempotent for same user; reject for another user.
            if ($lockedTicket->assigned_user_id !== null && (int) $lockedTicket->assigned_user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'ticket' => 'Este atendimento já foi assumido por outro atendente.',
                ]);
            }

            $lockedTicket->fill([
                'assigned_user_id' => $user->id,
                'status' => ServiceTicket::STATUS_ASSIGNED,
                'claimed_at' => $lockedTicket->claimed_at ?? now(),
            ])->save();

            if ($lockedTicket->lead_id) {
                $lead = Lead::query()->whereKey($lockedTicket->lead_id)->lockForUpdate()->firstOrFail();
                $this->pauseForClaim($lead, $user);
            }

            return $lockedTicket->fresh(['lead', 'assignedUser']);
        });

        try {
            event(new HumanHandoffClaimed(
                tenantId: (string) $result->tenant_id,
                ticketId: $result->id,
                leadId: $result->lead_id,
                assignedUserId: $result->assigned_user_id,
                assignedUserName: $result->assignedUser?->name,
            ));
            event(new AtendimentoCountersUpdated((string) $result->tenant_id, $user->id));
        } catch (\Throwable) {
        }

        return $result;
    }

    /**
     * Claim a conversation by lead: finds or creates the active escalation ticket,
     * then assigns both ticket and lead to the user atomically.
     */
    public function claimByLead(Lead $lead, User $user): ServiceTicket
    {
        $result = DB::transaction(function () use ($lead, $user): ServiceTicket {
            $lockedLead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($lockedLead->assigned_user_id !== null && (int) $lockedLead->assigned_user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'lead' => 'Esta conversa já foi assumida por outro atendente.',
                ]);
            }

            $ticket = ServiceTicket::query()
                ->activeEscalation($lockedLead->id)
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                // No escalation ticket exists — create one for this claim.
                $ticket = ServiceTicket::createAssignedEscalation($lockedLead, $user->id);
            } else {
                if ($ticket->assigned_user_id !== null && (int) $ticket->assigned_user_id !== (int) $user->id) {
                    throw ValidationException::withMessages([
                        'ticket' => 'Este atendimento já foi assumido por outro atendente.',
                    ]);
                }

                $ticket->fill([
                    'assigned_user_id' => $user->id,
                    'status' => ServiceTicket::STATUS_ASSIGNED,
                    'claimed_at' => $ticket->claimed_at ?? now(),
                ])->save();
            }

            $this->pauseForClaim($lockedLead, $user);

            return $ticket->fresh(['lead', 'assignedUser']);
        });

        try {
            event(new HumanHandoffClaimed(
                tenantId: (string) $result->tenant_id,
                ticketId: $result->id,
                leadId: $result->lead_id,
                assignedUserId: $result->assigned_user_id,
                assignedUserName: $result->assignedUser?->name,
            ));
            event(new AtendimentoCountersUpdated((string) $result->tenant_id, $user->id));
        } catch (\Throwable) {
        }

        return $result;
    }

    public function markHumanResponse(Lead $lead, ?User $user = null): ?ServiceTicket
    {
        $result = DB::transaction(function () use ($lead, $user): ?ServiceTicket {
            $ticket = ServiceTicket::query()
                ->where('lead_id', $lead->id)
                ->active()
                ->latest()
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            $updates = [
                'status' => ServiceTicket::STATUS_WAITING_CUSTOMER,
                'last_operator_message_at' => now(),
                'first_response_at' => $ticket->first_response_at ?? now(),
            ];

            if ($user && ! $ticket->assigned_user_id) {
                $updates['assigned_user_id'] = $user->id;
                $updates['claimed_at'] = $ticket->claimed_at ?? now();
            }

            $ticket->update($updates);

            $leadUpdates = [
                'operational_stage' => Lead::STAGE_WAITING_CUSTOMER,
            ];
            if ($user && ! $lead->assigned_user_id) {
                $leadUpdates['assigned_user_id'] = $user->id;
            }
            $lead->update($leadUpdates);

            return $ticket->fresh(['lead', 'assignedUser']);
        });

        if ($result !== null) {
            try {
                event(new AtendimentoCountersUpdated((string) $result->tenant_id, $user?->id));
            } catch (\Throwable) {
            }
        }

        return $result;
    }

    public function resolve(ServiceTicket $ticket, User $user, ?string $reason = null, ?string $notes = null): ServiceTicket
    {
        return $this->finalise($ticket, $user, ServiceTicket::STATUS_RESOLVED, $reason, $notes);
    }

    public function close(ServiceTicket $ticket, User $user, ?string $reason = null, ?string $notes = null): ServiceTicket
    {
        return $this->finalise($ticket, $user, ServiceTicket::STATUS_CLOSED, $reason, $notes);
    }

    private function finalise(ServiceTicket $ticket, User $user, string $status, ?string $reason, ?string $notes): ServiceTicket
    {
        $tenantId = (string) $ticket->tenant_id;
        $leadId = $ticket->lead_id;
        $timestampField = $status === ServiceTicket::STATUS_RESOLVED ? 'resolved_at' : 'closed_at';

        $result = DB::transaction(function () use ($ticket, $user, $status, $timestampField, $reason, $notes): ServiceTicket {
            $ticket->fill([
                'assigned_user_id' => $ticket->assigned_user_id ?? $user->id,
                'status' => $status,
                $timestampField => $ticket->{$timestampField} ?? now(),
                'resolution_reason' => $reason,
                'resolution_notes' => $notes,
            ])->save();

            $this->syncLeadConclusion($ticket, $reason);

            return $ticket->fresh(['lead', 'assignedUser']);
        });

        try {
            event(new HumanHandoffResolved(
                tenantId: $tenantId,
                ticketId: $result->id,
                leadId: $leadId,
                resolutionReason: $reason,
            ));
            event(new AtendimentoCountersUpdated($tenantId, $user->id));
        } catch (\Throwable) {
        }

        return $result;
    }

    /**
     * Resolve ticket and resume AI automation for the lead.
     * Clears pause, resets operational stage, reevaluates follow-up.
     */
    public function returnToAi(ServiceTicket $ticket, User $user): ServiceTicket
    {
        $tenantId = (string) $ticket->tenant_id;
        $leadId = $ticket->lead_id;

        $result = DB::transaction(function () use ($ticket, $user): ServiceTicket {
            $ticket->fill([
                'assigned_user_id' => $ticket->assigned_user_id ?? $user->id,
                'status' => ServiceTicket::STATUS_RESOLVED,
                'resolved_at' => $ticket->resolved_at ?? now(),
                'resolution_reason' => ServiceTicket::RESOLUTION_RETURNED_TO_AI,
            ])->save();

            $lead = $ticket->lead;
            if ($lead) {
                $this->pause->resume((string) $lead->whatsapp, (string) $lead->tenant_id);

                $lead->update([
                    'assigned_user_id' => null,
                    'ai_paused_until' => null,
                    'ai_paused_reason' => null,
                    'ai_paused_by' => null,
                    'operational_stage' => Lead::STAGE_AI_QUALIFYING,
                ]);

                $lead->refresh();
                $lead->activateFollowUp();
            }

            return $ticket->fresh(['lead', 'assignedUser']);
        });

        try {
            event(new HumanHandoffReturnedToAi(
                tenantId: $tenantId,
                ticketId: $result->id,
                leadId: $leadId,
                aiMode: $result->lead?->ai_mode,
            ));
            event(new AtendimentoCountersUpdated($tenantId, $user->id));
        } catch (\Throwable) {
        }

        return $result;
    }

    /**
     * Close ticket and keep AI paused/manual — lead stays in manual control.
     */
    public function keepManual(ServiceTicket $ticket, User $user): ServiceTicket
    {
        $tenantId = (string) $ticket->tenant_id;
        $leadId = $ticket->lead_id;

        $result = DB::transaction(function () use ($ticket, $user): ServiceTicket {
            $ticket->fill([
                'assigned_user_id' => $ticket->assigned_user_id ?? $user->id,
                'status' => ServiceTicket::STATUS_CLOSED,
                'closed_at' => $ticket->closed_at ?? now(),
                'resolution_reason' => ServiceTicket::RESOLUTION_MANUAL_KEEP,
            ])->save();

            // Keep AI paused — do not resume. Lead stays assigned.
            return $ticket->fresh(['lead', 'assignedUser']);
        });

        // Ticket left the active queue — refresh the atendimentos board.
        try {
            event(new HumanHandoffResolved(
                tenantId: $tenantId,
                ticketId: $result->id,
                leadId: $leadId,
                resolutionReason: ServiceTicket::RESOLUTION_MANUAL_KEEP,
            ));
            event(new AtendimentoCountersUpdated($tenantId, $user->id));
        } catch (\Throwable) {
        }

        return $result;
    }

    private function pauseForClaim(Lead $lead, User $user): void
    {
        $this->pause->pause(
            (string) $lead->whatsapp,
            (string) $lead->tenant_id,
            stage: Lead::STAGE_HUMAN_ACTIVE,
            reason: 'ticket_claimed',
            pausedBy: $user->id,
        );

        $lead->update(['assigned_user_id' => $user->id]);
    }

    private function syncLeadConclusion(ServiceTicket $ticket, ?string $reason): void
    {
        $lead = $ticket->lead;
        if (! $lead) {
            return;
        }

        $targetStatus = match ($reason) {
            'convertido' => 'convertido',
            'optou_sair' => 'optou_sair',
            default => null,
        };

        if ($targetStatus === null || $lead->status === $targetStatus || ! $lead->canTransitionTo($targetStatus)) {
            return;
        }

        $lead->update([
            'status' => $targetStatus,
            'followup_status' => 'inactive',
            'operational_stage' => $targetStatus === 'convertido' ? Lead::STAGE_WON : Lead::STAGE_LOST,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function inferPriority(string $type, array $data): string
    {
        if ($type === 'no_credit') {
            return ServiceTicket::PRIORITY_LOW;
        }

        return ServiceTicket::inferPriorityFromReason((string) Arr::get($data, 'reason', ''));
    }

    private function normalizePriority(string $priority): string
    {
        return in_array($priority, [
            ServiceTicket::PRIORITY_LOW,
            ServiceTicket::PRIORITY_NORMAL,
            ServiceTicket::PRIORITY_HIGH,
            ServiceTicket::PRIORITY_URGENT,
        ], true) ? $priority : ServiceTicket::PRIORITY_NORMAL;
    }
}
