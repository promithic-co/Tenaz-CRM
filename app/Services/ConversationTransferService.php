<?php

namespace App\Services;

use App\Events\AtendimentoCountersUpdated;
use App\Events\ConversationAssignmentChanged;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationTransferService
{
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
                $ticket = ServiceTicket::create([
                    'tenant_id' => (string) $lockedLead->tenant_id,
                    'lead_id' => $lockedLead->id,
                    'type' => ServiceTicket::TYPE_ESCALATION,
                    'status' => ServiceTicket::STATUS_ASSIGNED,
                    'priority' => ServiceTicket::PRIORITY_NORMAL,
                    'assigned_user_id' => $targetUser->id,
                    'claimed_at' => now(),
                    'sla_due_at' => now()->addHours(4),
                ]);
            } else {
                $ticket->fill([
                    'assigned_user_id' => $targetUser->id,
                    'status' => ServiceTicket::STATUS_ASSIGNED,
                    'claimed_at' => $ticket->claimed_at ?? now(),
                ])->save();
            }

            $ttl = 36000;
            Cache::put("pause:{$lockedLead->tenant_id}:{$lockedLead->whatsapp}", 'paused', $ttl);

            $lockedLead->update([
                'assigned_user_id' => $targetUser->id,
                'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
                'ai_paused_until' => now()->addSeconds($ttl),
                'ai_paused_reason' => 'conversation_transferred_to_user',
                'ai_paused_by' => $actor->id,
                'followup_status' => 'paused',
            ]);

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
