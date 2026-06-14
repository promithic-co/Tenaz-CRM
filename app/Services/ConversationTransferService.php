<?php

namespace App\Services;

use App\Events\AtendimentoCountersUpdated;
use App\Events\ConversationAssignmentChanged;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationTransferService
{
    public function __construct(private readonly PauseService $pause) {}

    /**
     * Transfer a conversation to a specific user (target_type=user).
     *
     * Creates or reuses the active escalation ticket, assigns ticket + lead
     * to the target user, pauses AI and follow-up atomically.
     *
     * @throws ValidationException when target user is from another tenant or transfer is not allowed
     */
    public function transferToUser(Lead $lead, User $actor, User $targetUser): ServiceTicket
    {
        if ((string) $targetUser->tenantId !== (string) $lead->tenant_id) {
            throw ValidationException::withMessages([
                'target_id' => 'Usuário de destino não pertence ao tenant.',
            ]);
        }

        $oldAssignedUserId = $lead->assigned_user_id;

        $result = DB::transaction(function () use ($lead, $actor, $targetUser): ServiceTicket {
            $lockedLead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            $ticket = ServiceTicket::query()
                ->activeEscalation($lockedLead->id)
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                $ticket = ServiceTicket::createAssignedEscalation($lockedLead, $targetUser->id);
            } else {
                $ticket->fill([
                    'assigned_user_id' => $targetUser->id,
                    'status' => ServiceTicket::STATUS_ASSIGNED,
                    'claimed_at' => $ticket->claimed_at ?? now(),
                ])->save();
            }

            $this->pause->pause(
                (string) $lockedLead->whatsapp,
                (string) $lockedLead->tenant_id,
                stage: Lead::STAGE_HUMAN_ACTIVE,
                reason: 'conversation_transferred_to_user',
                pausedBy: $actor->id,
                followupStatus: 'paused',
            );

            $lockedLead->update(['assigned_user_id' => $targetUser->id]);

            return $ticket->fresh(['lead', 'assignedUser']);
        });

        try {
            event(new ConversationAssignmentChanged(
                tenantId: (string) $lead->tenant_id,
                leadId: $lead->id,
                oldAssignedUserId: $oldAssignedUserId,
                newAssignedUserId: $targetUser->id,
                operationalStage: $result->lead?->operational_stage,
            ));
            event(new AtendimentoCountersUpdated((string) $lead->tenant_id, $targetUser->id));
        } catch (\Throwable) {
        }

        return $result;
    }
}
