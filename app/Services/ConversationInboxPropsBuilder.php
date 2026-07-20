<?php

namespace App\Services;

use App\Http\Requests\InboxFilterRequest;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ConversationInboxPropsBuilder
{
    public function __construct(
        private readonly ConversationAutomationService $automation,
        private readonly ConversationPanelPropsBuilder $panelProps,
        private readonly ConversationTransferTargetsBuilder $transferTargets,
    ) {}

    /**
     * @return array{
     *     leads: mixed,
     *     filters: array<string, string>,
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

        $paginator = $this->buildInboxQuery($filters, $tenantId, $actor)
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
        ]);

        return [
            'leads' => $leads,
            'filters' => $filters,
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
     * @param  Collection<int, Lead>  $leads
     * @return array<string, bool>
     */
    private function pauseMapFor(Collection $leads, string $tenantId): array
    {
        $pauseKeys = $leads->map(fn (Lead $lead): string => "pause:{$tenantId}:{$lead->whatsapp}")->all();
        $pauseCacheValues = $pauseKeys === [] ? [] : Cache::many($pauseKeys);
        $pauseMap = [];

        foreach ($leads as $lead) {
            $cached = $pauseCacheValues["pause:{$tenantId}:{$lead->whatsapp}"] ?? null;
            $pauseMap[$lead->whatsapp] = $cached !== null
                || ($lead->ai_paused_until !== null && $lead->ai_paused_until->isFuture());
        }

        return $pauseMap;
    }
}
