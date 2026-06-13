<?php

namespace App\Services;

use App\Http\Resources\ServiceTicketResource;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the atendimentos (escalation handoff) board: the four ticket/lead
 * buckets and their counters for a tenant, honouring the restricted-user
 * visibility scope.
 */
class ServiceTicketBoardService
{
    /**
     * @param  array{motivo?: string, data_inicio?: string, data_fim?: string}  $filters
     * @return array{
     *     buckets: array{waiting: LengthAwarePaginator, mine: LengthAwarePaginator, ai: Collection<int, array<string, mixed>>, closed: LengthAwarePaginator},
     *     counters: array{waiting: int, mine: int, ai: int, closed: int, overdue: int}
     * }
     */
    public function build(User $user, array $filters): array
    {
        $tenantId = $user->tenantId;
        $userId = $user->id;
        $isRestricted = $user->isRestrictedUser();

        $motivo = (string) ($filters['motivo'] ?? '');
        $dataInicio = (string) ($filters['data_inicio'] ?? '');
        $dataFim = (string) ($filters['data_fim'] ?? '');

        // Base escalation ticket query for this tenant (+ restricted user scope).
        $base = ServiceTicket::forTenant($tenantId)
            ->where('type', ServiceTicket::TYPE_ESCALATION)
            ->with(['lead', 'lead.tags:id,name,color,slug,is_hot', 'assignedUser']);

        if ($isRestricted) {
            $base->where(function ($q) use ($userId): void {
                $q->where('assigned_user_id', $userId)
                    ->orWhereNull('assigned_user_id')
                    ->orWhereHas('lead', fn ($lq) => $lq->where('assigned_user_id', $userId));
            });
        }

        if (! empty($motivo)) {
            $base->where(function ($q) use ($motivo): void {
                $q->where('reason', 'like', "%{$motivo}%")
                    ->orWhere('summary', 'like', "%{$motivo}%");
            });
        }

        if (! empty($dataInicio)) {
            $base->whereDate('created_at', '>=', $dataInicio);
        }

        if (! empty($dataFim)) {
            $base->whereDate('created_at', '<=', $dataFim);
        }

        $waitingQuery = (clone $base)
            ->where('status', ServiceTicket::STATUS_OPEN)
            ->whereNull('assigned_user_id')
            ->latest();

        $mineQuery = (clone $base)
            ->whereIn('status', [
                ServiceTicket::STATUS_ASSIGNED,
                ServiceTicket::STATUS_WAITING_CUSTOMER,
                ServiceTicket::STATUS_WAITING_INTERNAL,
            ])
            ->where('assigned_user_id', $userId)
            ->latest();

        $closedQuery = (clone $base)
            ->whereIn('status', [ServiceTicket::STATUS_RESOLVED, ServiceTicket::STATUS_CLOSED])
            ->latest();

        $mapper = fn (ServiceTicket $t): array => (new ServiceTicketResource($t))->resolve();

        $waiting = $waitingQuery->paginate(15, ['*'], 'waiting_page')->through($mapper);
        $mine = $mineQuery->paginate(15, ['*'], 'mine_page')->through($mapper);
        $closed = $closedQuery->paginate(15, ['*'], 'closed_page')->through($mapper);

        $aiLeads = $this->buildAiBucket($tenantId, $isRestricted, $userId);

        $overdueCount = ServiceTicket::forTenant($tenantId)
            ->where('type', ServiceTicket::TYPE_ESCALATION)
            ->whereIn('status', ServiceTicket::ACTIVE_STATUSES)
            ->where('sla_due_at', '<', now())
            ->count();

        return [
            'buckets' => [
                'waiting' => $waiting,
                'mine' => $mine,
                'ai' => $aiLeads,
                'closed' => $closed,
            ],
            'counters' => [
                // The three LengthAware buckets derive their counter from the
                // paginator total so it reflects the full result set, not the
                // current page slice.
                'waiting' => $waiting->total(),
                'mine' => $mine->total(),
                // ai is capped at 20 rows by design; overdue is its own COUNT —
                // neither can be derived from a paginator total.
                'ai' => $aiLeads->count(),
                'closed' => $closed->total(),
                'overdue' => $overdueCount,
            ],
        ];
    }

    /**
     * AI bucket: leads with no active escalation ticket, in AI-driven stages.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildAiBucket(?string $tenantId, bool $isRestricted, int $userId): Collection
    {
        return Lead::production()
            ->forTenant($tenantId)
            ->whereIn('operational_stage', [
                Lead::STAGE_AI_QUALIFYING,
                Lead::STAGE_AI_FOLLOWUP,
                Lead::STAGE_QUALIFIED_OPPORTUNITY,
                Lead::STAGE_NEW_INBOUND,
            ])
            ->whereDoesntHave('tickets', function (Builder $q): void {
                $q->where('type', ServiceTicket::TYPE_ESCALATION)
                    ->whereIn('status', ServiceTicket::ACTIVE_STATUSES);
            })
            ->when($isRestricted, function ($q) use ($userId): void {
                $q->where(function ($inner) use ($userId): void {
                    $inner->where('assigned_user_id', $userId)
                        ->orWhereHas('agent', fn ($aq) => $aq->where('user_id', $userId));
                });
            })
            ->with(['assignedUser'])
            ->latest('last_interaction_at')
            ->limit(20)
            ->get()
            ->map(fn (Lead $l): array => [
                'id' => $l->id,
                'nome' => $l->nome ?? $l->whatsapp,
                'whatsapp' => $l->whatsapp,
                'status' => $l->status,
                'operational_stage' => $l->operational_stage,
                'ai_mode' => $l->ai_mode,
                'followup_status' => $l->followup_status,
                'assigned_user_name' => $l->assignedUser?->name,
                'ultima_interacao' => $l->last_interaction_at?->diffForHumans(),
            ]);
    }
}
