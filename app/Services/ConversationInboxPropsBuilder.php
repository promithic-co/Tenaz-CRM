<?php

namespace App\Services;

use App\Http\Requests\InboxFilterRequest;
use App\Models\ConversationSession;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ConversationInboxPropsBuilder
{
    public function __construct(
        private readonly ConversationAutomationService $automation,
        private readonly ConversationPanelPropsBuilder $panelProps,
        private readonly ConversationTransferTargetsBuilder $transferTargets,
    ) {}

    /** Longest last-message preview shipped to the sidebar row. */
    private const PREVIEW_LENGTH = 120;

    /**
     * @return array{
     *     leads: mixed,
     *     filters: array<string, string>,
     *     group_counts: array<string, int>,
     *     instances: mixed,
     *     transfer_targets: list<array{type: string, id: int, name: string}>,
     *     activeConversation: array<string, mixed>|null
     * }
     */
    public function build(InboxFilterRequest $request, ?Lead $activeLead = null): array
    {
        /** @var User $actor */
        $actor = $request->user();
        $filters = $request->filters();
        $tenantId = (string) $actor->tenantId;

        $instances = WhatsappInstance::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('display_name')
            ->orderBy('name')
            ->get(['name', 'display_name']);

        $paginator = $this->withLastMessage($this->buildInboxQuery($filters, $tenantId, $actor))
            ->paginate(15)
            ->withQueryString();

        $pageLeads = $paginator->getCollection();
        $effectiveModes = $this->automation->resolveModesByInstanceId($pageLeads);
        $pauseMap = $this->pauseMapFor($pageLeads, $tenantId);

        $leads = $paginator->through(fn (Lead $lead): array => [
            'id' => $lead->id,
            'nome' => $lead->nome ?? $lead->whatsapp,
            'whatsapp' => $lead->whatsapp,
            'status' => $lead->status,
            'followup_status' => $lead->followup_status,
            'followup_count' => $lead->followup_count,
            'ai_mode' => $lead->ai_mode,
            'effective_ai_mode' => $effectiveModes[$lead->id],
            'operational_stage' => $lead->operational_stage,
            'assigned_user_id' => $lead->assigned_user_id,
            'assigned_user_name' => $lead->assignedUser?->name,
            'ultima_interacao' => $lead->last_interaction_at?->diffForHumans() ?? $lead->created_at?->diffForHumans(),
            'pausado' => $pauseMap[$lead->whatsapp] ?? false,
            'whatsapp_instance_id' => $lead->whatsapp_instance_id,
            'is_returning' => $lead->openSession !== null
                && in_array($lead->openSession->open_reason, ConversationSession::REENGAGEMENT_REASONS, true),
            'last_message_body' => $this->previewOf($lead->last_message_body),
            'last_message_direction' => $lead->last_message_direction,
            'awaiting_reply' => $lead->last_message_direction === 'inbound',
        ]);

        return [
            /**
             * Deep-merged so the sidebar can scroll infinitely: a partial reload
             * asking for the next page appends to leads.data instead of replacing
             * it, while the paginator meta (current_page, total) takes the newest
             * value. Matching on id keeps a lead from doubling up when it moves
             * between pages. A full visit — every tab and filter change — is not a
             * partial reload, so the list resets on its own.
             */
            'leads' => Inertia::deepMerge($leads)->matchOn(['leads.data.id', 'leads.links.label']),
            'filters' => $filters,
            'group_counts' => $this->groupCountsFor($filters, $tenantId, $actor),
            'instances' => $instances->map(fn (WhatsappInstance $instance): array => [
                'name' => $instance->name,
                'label' => $instance->label(),
            ]),
            'transfer_targets' => $this->transferTargets->forTenant($tenantId, $actor),
            'activeConversation' => $activeLead ? $this->panelProps->build($activeLead, $actor) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildInboxQuery(array $filters, string $tenantId, User $actor): Builder
    {
        $query = Lead::production()
            ->forTenant($tenantId)
            ->with(['assignedUser', 'tags:id,name,color,slug,is_hot', 'openSession']);

        if ($filters['instance'] !== '') {
            $instanceId = WhatsappInstance::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $filters['instance'])
                ->value('id');

            $query->where('whatsapp_instance_id', $instanceId ?? 0);
        }

        return $query->visibleTo($actor)->inboxFiltered($filters);
    }

    /**
     * Attach the last timeline message of every row as correlated subselects, so
     * the sidebar can show a preview without a query per lead.
     *
     * Ordered by created_at (id as tiebreak) rather than by max(id): the campaign
     * timeline backfill inserts historic messages with fresh ids, so id order and
     * chronological order do not agree. Backed by the (lead_id, created_at) index.
     */
    private function withLastMessage(Builder $query): Builder
    {
        $lastMessage = fn (string $column) => ConversationTimelineMessage::query()
            ->select($column)
            ->whereColumn('lead_id', 'leads.id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(1);

        return $query->addSelect([
            'last_message_body' => $lastMessage('body'),
            'last_message_direction' => $lastMessage('direction'),
        ]);
    }

    private function previewOf(?string $body): ?string
    {
        $body = trim((string) $body);

        return $body === '' ? null : Str::limit($body, self::PREVIEW_LENGTH);
    }

    /**
     * Badge counts for the inbox tabs.
     *
     * Counts conversations waiting on a reply, not rows. A badge that reports the size of
     * a tab tells the operator nothing they cannot see by looking at it, and it never
     * reaches zero — so it stops being read. Waiting-on-us is the number worth interrupting
     * someone with, and it clears as the work gets done.
     *
     * "envios" is the exception, and by its own definition: a silent campaign send has no
     * inbound at all, so an awaiting-reply count there is always zero. What that tab
     * measures is how much went out, so it keeps reporting its size.
     *
     * Reuses buildInboxQuery so the counters inherit visibleTo: a restricted user
     * must never be told about leads they cannot open. Every filter except the
     * group itself is kept, so a tab badge always counts within the same population
     * that tab actually renders.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function groupCountsFor(array $filters, string $tenantId, User $actor): array
    {
        $counts = [];

        // The group goes through $filters rather than a second inGroup() on top of an
        // "todas" query: scopeInboxFiltered decides per group whether to subtract the
        // silent campaign sends, and stacking the two produced a self-contradicting
        // query for "envios" (excluded by the base, required by the group) that
        // counted zero no matter how many had been sent.
        foreach ([Lead::INBOX_GROUP_ALL, ...Lead::INBOX_GROUPS] as $group) {
            $query = $this->buildInboxQuery(
                [...$filters, 'group' => $group],
                $tenantId,
                $actor,
            );

            $counts[$group] = $group === Lead::INBOX_GROUP_SENDS
                ? $query->count()
                : $query->awaitingReply()->count();
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array<string, bool>
     */
    private function pauseMapFor(Collection $leads, string $tenantId): array
    {
        // Through PauseService so the key is built the one way it is written — the cache
        // side of a pause is keyed on the canonical spelling, and rebuilding it from the
        // lead's raw whatsapp missed every row stored under the other 9th-digit form.
        $pauseKeys = $leads->map(fn (Lead $lead): string => PauseService::cacheKey($tenantId, $lead->whatsapp))->all();
        $pauseCacheValues = $pauseKeys === [] ? [] : Cache::many($pauseKeys);
        $pauseMap = [];

        foreach ($leads as $lead) {
            $cached = $pauseCacheValues[PauseService::cacheKey($tenantId, $lead->whatsapp)] ?? null;
            $pauseMap[$lead->whatsapp] = $cached !== null
                || ($lead->ai_paused_until !== null && $lead->ai_paused_until->isFuture());
        }

        return $pauseMap;
    }
}
