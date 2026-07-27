<?php

namespace App\Services;

use App\Http\Resources\LeadCardResource;
use App\Models\Agent;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\StatusMachine;
use App\Models\Tag;
use App\Models\WhatsappInstance;
use Illuminate\Database\Eloquent\Builder;

class PipelineBoardPropsBuilder
{
    /**
     * @var list<string>
     */
    private const HIDDEN_BOARD_STATUS_SLUGS = [
        'sem_credito',
    ];

    public function __construct(
        private readonly ConversationAutomationService $automation,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     statuses: list<array<string, mixed>>,
     *     columns: array<string, array{data: list<array<string, mixed>>, next_cursor: string|null, count: int, value_cents: int}>,
     *     filters: array<string, mixed>,
     *     tenantId: string,
     *     agents: mixed,
     *     instances: mixed,
     *     tagsCatalog: mixed
     * }
     */
    public function buildIndex(array $filters, string $tenantId): array
    {
        $machine = StatusMachine::forTenant($tenantId);
        $statuses = $this->boardStatuses($machine);
        $visibleStatusSlugs = collect($statuses)->pluck('slug')->all();

        $aggregates = $this->columnAggregates($filters, $tenantId, $visibleStatusSlugs);

        $columnPaginators = [];
        foreach ($statuses as $status) {
            $columnPaginators[$status['slug']] = $this->buildLeadQuery($filters, $tenantId)
                ->where('status', $status['slug'])
                ->cursorPaginate(30);
        }

        $effectiveModes = $this->automation->resolveModesByInstanceName(
            collect($columnPaginators)->flatMap(fn ($paginator) => $paginator->getCollection())
        );

        $columns = [];
        foreach ($columnPaginators as $slug => $paginator) {
            $columns[$slug] = [
                'data' => $paginator->getCollection()
                    ->map(fn (Lead $lead): array => $this->toCardShape($lead, $effectiveModes))
                    ->values()
                    ->all(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'count' => (int) ($aggregates[$slug]['count'] ?? 0),
                'value_cents' => (int) ($aggregates[$slug]['value_cents'] ?? 0),
            ];
        }

        return [
            'statuses' => $statuses,
            'columns' => $columns,
            'filters' => $filters,
            'tenantId' => $tenantId,
            'agents' => Agent::query()
                ->where('tenant_id', $tenantId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'instances' => WhatsappInstance::query()
                ->where('tenant_id', $tenantId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'tagsCatalog' => Tag::query()
                ->where('tenant_id', $tenantId)
                ->select('id', 'name', 'slug', 'color')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function buildColumn(array $filters, string $tenantId, string $slug): array
    {
        $machine = StatusMachine::forTenant($tenantId);
        $validSlugs = collect($this->boardStatuses($machine))->pluck('slug');

        abort_unless($validSlugs->contains($slug), 404);

        $paginator = $this->buildLeadQuery($filters, $tenantId)
            ->where('status', $slug)
            ->cursorPaginate(30);

        $effectiveModes = $this->automation->resolveModesByInstanceName($paginator->getCollection());

        return [
            'data' => $paginator->getCollection()
                ->map(fn (Lead $lead): array => $this->toCardShape($lead, $effectiveModes))
                ->values()
                ->all(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
        ];
    }

    /**
     * Per-column card count and open-atendimento money total, in one grouped pass.
     *
     * The money side is a correlated subquery rather than a join: `buildLeadQuery` writes its
     * filters with unqualified column names, and `conversation_sessions` also has `status`,
     * `tenant_id` and `created_at` — joining it would make those filters ambiguous. `LIMIT 1`
     * is exact, not a guess: a partial unique index allows at most one open session per lead.
     *
     * The totals cover every matching lead, not just the ~30 the column has lazily loaded, so
     * the header stays honest as the operator scrolls.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $visibleStatusSlugs
     * @return array<string, array{count: int, value_cents: int}>
     */
    private function columnAggregates(array $filters, string $tenantId, array $visibleStatusSlugs): array
    {
        $openValue = <<<'SQL'
            (
                select cs.value_cents
                from conversation_sessions cs
                where cs.lead_id = leads.id
                  and cs.status = ?
                  and cs.deleted_at is null
                limit 1
            )
        SQL;

        return $this->buildLeadQuery($filters, $tenantId)
            ->without(['campaign', 'tags', 'openSession'])
            ->reorder()
            ->whereIn('status', $visibleStatusSlugs)
            ->groupBy('status')
            ->selectRaw(
                "status, COUNT(*) as total, COALESCE(SUM({$openValue}), 0) as value_cents",
                [ConversationSession::STATUS_OPEN],
            )
            ->get()
            ->mapWithKeys(fn (Lead $row): array => [
                (string) $row->status => [
                    'count' => (int) $row->total,
                    'value_cents' => (int) $row->value_cents,
                ],
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function boardStatuses(StatusMachine $machine): array
    {
        return $machine->getStatuses()
            ->reject(fn (array $status): bool => in_array((string) $status['slug'], self::HIDDEN_BOARD_STATUS_SLUGS, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildLeadQuery(array $filters, string $tenantId): Builder
    {
        $query = Lead::query()
            ->where('tenant_id', $tenantId)
            ->production()
            ->with([
                'campaign:id,name',
                'tags:id,name,color,slug,is_hot',
                // One extra query for the whole page, not one per card: the board renders a
                // column's worth of leads and each card shows the open atendimento's amount.
                'openSession:id,lead_id,value_cents,expected_close_at',
            ])
            ->orderByDesc('last_interaction_at');

        if (! empty($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }
        if (! empty($filters['instance_id'])) {
            $query->where('whatsapp_instance_id', $filters['instance_id']);
        }
        if (! empty($filters['tags'])) {
            $query->whereHas('tags', fn (Builder $query) => $query->whereIn('tags.id', $filters['tags']));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('last_interaction_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('last_interaction_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $query->where('nome', 'like', '%'.$filters['search'].'%');
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $effectiveModes
     * @return array<string, mixed>
     */
    public function toCardShape(Lead $lead, array $effectiveModes): array
    {
        $effectiveAiMode = $effectiveModes[$lead->id] ?? Lead::AI_MODE_MANUAL;
        $automationState = ! $lead->isAiPaused() && in_array($effectiveAiMode, [
            Lead::AI_MODE_AUTOMATIC,
            Lead::AI_MODE_QUALIFY_THEN_HANDOFF,
        ], true) ? 'active' : 'manual';

        return (new LeadCardResource($lead, $automationState, $this->sourceLabel($lead)))
            ->resolve();
    }

    public function sourceLabel(Lead $lead): string
    {
        if ($lead->campaign?->name) {
            return $lead->campaign->name;
        }

        return match ($lead->modo) {
            'bulk' => 'Campanha',
            'receptivo' => 'Receptivo',
            default => $lead->modo ? ucfirst((string) $lead->modo) : 'Sem origem',
        };
    }
}
