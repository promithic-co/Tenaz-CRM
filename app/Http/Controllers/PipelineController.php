<?php

namespace App\Http\Controllers;

use App\Http\Requests\ColumnPipelineRequest;
use App\Http\Requests\IndexPipelineRequest;
use App\Http\Requests\MoveLeadRequest;
use App\Http\Resources\LeadCardResource;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\StatusMachine;
use App\Models\Tag;
use App\Models\WhatsappInstance;
use App\Services\ConversationAutomationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function __construct(
        private readonly ConversationAutomationService $automation,
    ) {}

    /**
     * @var list<string>
     */
    private const HIDDEN_BOARD_STATUS_SLUGS = [
        'sem_credito',
    ];

    public function index(IndexPipelineRequest $request): Response
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = StatusMachine::forTenant($tenantId);
        $statuses = $this->boardStatuses($machine);
        $visibleStatusSlugs = collect($statuses)->pluck('slug')->all();
        $filters = $request->validated();

        $counts = $this->buildLeadQuery($filters, $tenantId)
            ->without(['campaign', 'tags'])
            ->reorder()
            ->whereIn('status', $visibleStatusSlugs)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        // Fetch each column's page first, then bulk-resolve instance AI modes in one query.
        $columnPaginators = [];
        foreach ($statuses as $status) {
            $columnPaginators[$status['slug']] = $this->buildLeadQuery($filters, $tenantId)
                ->where('status', $status['slug'])
                ->cursorPaginate(30);
        }

        $effectiveModes = $this->automation->resolveModesByInstanceName(
            collect($columnPaginators)->flatMap(fn ($p) => $p->getCollection())
        );

        $columns = [];
        foreach ($columnPaginators as $slug => $paginator) {
            $columns[$slug] = [
                'data' => $paginator->getCollection()
                    ->map(fn (Lead $lead): array => $this->toCardShape($lead, $effectiveModes))
                    ->values()
                    ->all(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'count' => $counts[$slug] ?? 0,
            ];
        }

        $agents = Agent::query()
            ->where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $instances = WhatsappInstance::query()
            ->where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $tagsCatalog = Tag::query()
            ->where('tenant_id', $tenantId)
            ->select('id', 'name', 'slug', 'color')
            ->orderBy('name')
            ->get();

        return Inertia::render('pipeline/Index', [
            'statuses' => $statuses,
            'columns' => $columns,
            'filters' => $filters,
            'tenantId' => $tenantId,
            'agents' => $agents,
            'instances' => $instances,
            'tagsCatalog' => $tagsCatalog,
        ]);
    }

    public function column(ColumnPipelineRequest $request, string $slug): JsonResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = StatusMachine::forTenant($tenantId);

        $validSlugs = collect($this->boardStatuses($machine))->pluck('slug');
        abort_unless($validSlugs->contains($slug), 404);

        $paginator = $this->buildLeadQuery($request->validated(), $tenantId)
            ->where('status', $slug)
            ->cursorPaginate(30);

        $effectiveModes = $this->automation->resolveModesByInstanceName($paginator->getCollection());

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Lead $lead): array => $this->toCardShape($lead, $effectiveModes))
                ->values()
                ->all(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
        ]);
    }

    public function move(MoveLeadRequest $request): RedirectResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = StatusMachine::forTenant($tenantId);
        $fromStatus = (string) $request->string('from_status');
        $toStatus = (string) $request->string('to_status');
        $visibleStatusSlugs = collect($this->boardStatuses($machine))->pluck('slug')->all();

        $lead = Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $request->integer('lead_id'))
            ->firstOrFail();

        if (! in_array($toStatus, $visibleStatusSlugs, true)) {
            return back()->withErrors([
                'to_status' => 'Status de destino nao esta disponivel no Kanban.',
            ]);
        }

        if (! in_array((string) $lead->status, $visibleStatusSlugs, true)) {
            return back()->withErrors([
                'from_status' => 'Status atual do lead nao esta disponivel no Kanban.',
            ]);
        }

        if ((string) $lead->status !== $fromStatus) {
            return back()->withErrors([
                'from_status' => 'Status do lead mudou. Recarregue o Kanban e tente novamente.',
            ]);
        }

        $lead->update([
            'status' => $toStatus,
            'ai_paused_until' => now()->addHours(24),
            'ai_paused_by' => $request->user()->id,
            'ai_paused_reason' => 'manual_status_override',
        ]);

        return back();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function boardStatuses(StatusMachine $machine): array
    {
        return $machine->getStatuses()
            ->reject(fn (array $status): bool => in_array((string) $status['slug'], self::HIDDEN_BOARD_STATUS_SLUGS, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function buildLeadQuery(array $filters, string $tenantId): Builder
    {
        $query = Lead::query()
            ->where('tenant_id', $tenantId)
            ->production()
            ->with([
                'campaign:id,name',
                'tags:id,name,color,slug,is_hot',
            ])
            ->orderByDesc('last_interaction_at');

        if (! empty($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }
        if (! empty($filters['instance_id'])) {
            $query->where('whatsapp_instance_id', $filters['instance_id']);
        }
        if (! empty($filters['tags'])) {
            $query->whereHas('tags', fn (Builder $q) => $q->whereIn('tags.id', $filters['tags']));
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
     * Build the pipeline card array for a lead. The automation_state and
     * source_label are derived here (they depend on the batch mode map and the
     * campaign relation) and injected into the pure-shape LeadCardResource.
     *
     * @param  array<int, string>  $effectiveModes  [leadId => effectiveAiMode] from resolveModesByInstanceName
     * @return array<string, mixed>
     */
    protected function toCardShape(Lead $lead, array $effectiveModes): array
    {
        $effectiveAiMode = $effectiveModes[$lead->id] ?? Lead::AI_MODE_MANUAL;
        $automationState = ! $lead->isAiPaused() && in_array($effectiveAiMode, [
            Lead::AI_MODE_AUTOMATIC,
            Lead::AI_MODE_QUALIFY_THEN_HANDOFF,
        ], true) ? 'active' : 'manual';

        return (new LeadCardResource($lead, $automationState, $this->sourceLabel($lead)))
            ->resolve();
    }

    protected function sourceLabel(Lead $lead): string
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
