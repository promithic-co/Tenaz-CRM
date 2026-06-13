<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\ServiceTicket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AtendimentoCountersUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $waiting;

    public readonly int $mine;

    public readonly int $ai;

    public readonly int $overdue;

    public function __construct(public readonly string $tenantId, ?int $userId = null)
    {
        $base = ServiceTicket::forTenant($tenantId)->where('type', ServiceTicket::TYPE_ESCALATION);

        $this->waiting = (clone $base)
            ->where('status', ServiceTicket::STATUS_OPEN)
            ->whereNull('assigned_user_id')
            ->count();

        $this->mine = $userId
            ? (clone $base)
                ->whereIn('status', [
                    ServiceTicket::STATUS_ASSIGNED,
                    ServiceTicket::STATUS_WAITING_CUSTOMER,
                    ServiceTicket::STATUS_WAITING_INTERNAL,
                ])
                ->where('assigned_user_id', $userId)
                ->count()
            : 0;

        $this->ai = Lead::production()
            ->forTenant($tenantId)
            ->whereIn('operational_stage', [
                Lead::STAGE_AI_QUALIFYING,
                Lead::STAGE_AI_FOLLOWUP,
                Lead::STAGE_QUALIFIED_OPPORTUNITY,
                Lead::STAGE_NEW_INBOUND,
            ])
            ->whereDoesntHave('tickets', function ($q): void {
                $q->where('type', ServiceTicket::TYPE_ESCALATION)
                    ->whereIn('status', ServiceTicket::ACTIVE_STATUSES);
            })
            ->count();

        $this->overdue = (clone $base)
            ->whereIn('status', ServiceTicket::ACTIVE_STATUSES)
            ->where('sla_due_at', '<', now())
            ->count();
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("atendimentos.{$this->tenantId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'atendimento.counters.updated';
    }
}
