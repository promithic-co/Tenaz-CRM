<?php

namespace App\Services;

use App\Enums\TemplateKind;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\WhatsappTemplateRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class CampaignPagePropsBuilder
{
    public function __construct(
        private readonly WhatsappTemplateRenderer $renderer,
    ) {}

    /**
     * FE-02: the create page only ever reads filters_json / template body for the
     * single row the user selects, yet they are the heaviest fields and grow with
     * tenant maturity (filters_json is arbitrary-size JSON per dynamic list). Ship
     * the lightweight option lists eagerly and defer those two heavy maps so they
     * load out of the initial render instead of bloating every page open.
     *
     * @return array{
     *     contactLists: mixed,
     *     templates: mixed,
     *     instances: mixed,
     *     contactListFilters: mixed,
     *     templateBodies: mixed,
     *     defaults: array{contact_list_id: int|null, whatsapp_instance_id: int|null}
     * }
     */
    public function create(Request $request): array
    {
        return [
            'contactLists' => ContactList::query()
                ->get(['id', 'name', 'is_dynamic', 'entries_count', 'last_resolved_count', 'last_resolved_at']),
            'templates' => $this->selectableTemplates()
                ->get(['id', 'name', 'kind', 'element_name', 'variables_count', 'whatsapp_instance_id']),
            'instances' => WhatsappInstance::query()->get(['id', 'name', 'display_name', 'provider']),
            'contactListFilters' => Inertia::defer(fn () => ContactList::query()
                ->whereNotNull('filters_json')
                ->pluck('filters_json', 'id')),
            'templateBodies' => Inertia::defer(fn () => $this->selectableTemplates()
                ->whereNotNull('body')
                ->pluck('body', 'id')),
            'defaults' => [
                'contact_list_id' => $request->integer('contact_list_id') ?: null,
                'whatsapp_instance_id' => $request->integer('whatsapp_instance_id') ?: null,
            ],
        ];
    }

    /**
     * @return array{
     *     campaign: Campaign,
     *     messages: mixed,
     *     repliedCount: int,
     *     statusCounts: array<string, int>,
     *     dailyBudget: array{sent_today: int, daily_limit: int, remaining: int},
     *     filters: array{status: string|null, search: string|null}
     * }
     */
    public function show(Campaign $campaign, Request $request): array
    {
        $campaign->load([
            'contactList:id,name',
            // components_json backs the per-recipient message preview below.
            'whatsappTemplate:id,name,body,variables_count,components_json',
            'whatsappInstance:id,name,display_name,meta_quality_rating',
        ]);

        return [
            'campaign' => $campaign,
            'messages' => $this->messagesQuery($campaign, $request)
                ->orderByDesc('sent_at')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (CampaignMessage $message) => $this->withRenderedBody($message, $campaign)),
            // whereNotNull('last_inbound_at') is what keeps this a reply count: every
            // recipient now gets a Lead stamped with campaign_id at send time, so the
            // foreign key alone would report the whole list as having answered.
            'repliedCount' => Lead::where('campaign_id', $campaign->id)
                ->whereNotNull('last_inbound_at')
                ->count(),
            'statusCounts' => $this->statusCounts($campaign),
            'dailyBudget' => $this->dailyBudget($campaign),
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
            ],
        ];
    }

    /**
     * Attach the message text this specific recipient received.
     *
     * The recipients table could previously report that a send was delivered without ever
     * showing what was delivered — and with a parameterised template every row differs, so
     * reading the template itself does not answer it either.
     *
     * Rendered per row rather than stored: template_params_resolved is the durable record,
     * and re-deriving keeps this in step with the renderer the send path uses. Null when the
     * template carries no structured components or the renderer rejects them; the operator
     * still gets the raw resolved values below.
     */
    private function withRenderedBody(CampaignMessage $message, Campaign $campaign): CampaignMessage
    {
        $components = $campaign->whatsappTemplate?->components_json;
        $resolved = is_array($message->template_params_resolved) ? $message->template_params_resolved : [];

        $text = null;

        if (is_array($components) && $components !== []) {
            try {
                $text = $this->renderer->preview($components, [
                    'header' => $resolved,
                    'body' => $resolved,
                    'buttons' => $resolved,
                ])['text'];
            } catch (Throwable) {
                $text = null;
            }
        }

        $message->setAttribute('rendered_message', $text ?? (trim(implode(' ', $resolved)) ?: null));

        return $message;
    }

    /**
     * Filtered recipient query shared by the Show table and the CSV export, so both honour
     * the same status + free-text (name/phone) filters. Eager-loads the entry the views read.
     *
     * @return HasMany<CampaignMessage, Campaign>
     */
    public function messagesQuery(Campaign $campaign, Request $request): HasMany
    {
        $query = $campaign->messages()->with(['contactListEntry:id,name,phone']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $digits = preg_replace('/\D+/', '', $term);

            $query->whereHas('contactListEntry', function (Builder $entry) use ($term, $digits): void {
                $entry->where('name', 'like', "%{$term}%");

                if ($digits !== '' && $digits !== null) {
                    $entry->orWhere('phone', 'like', "%{$digits}%");
                }
            });
        }

        return $query;
    }

    /**
     * Per-status recipient counts backing the clickable status cards. Every known status is
     * present (zero-filled) so the frontend never has to guess which keys exist.
     *
     * @return array<string, int>
     */
    private function statusCounts(Campaign $campaign): array
    {
        $counts = $campaign->messages()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statuses = ['pending', 'queued', 'in_doubt', 'sent', 'delivered', 'read', 'failed', 'skipped'];

        $result = [];

        foreach ($statuses as $status) {
            $result[$status] = (int) ($counts[$status] ?? 0);
        }

        return $result;
    }

    /**
     * Today's send budget: how many sends the campaign has spent today against its daily_limit.
     *
     * @return array{sent_today: int, daily_limit: int, remaining: int}
     */
    private function dailyBudget(Campaign $campaign): array
    {
        $sentToday = $campaign->messages()
            ->whereBetween('sent_at', [today()->startOfDay(), today()->endOfDay()])
            ->count();

        return [
            'sent_today' => $sentToday,
            'daily_limit' => (int) $campaign->daily_limit,
            'remaining' => max(0, (int) $campaign->daily_limit - $sentToday),
        ];
    }

    /** @return Builder<WhatsappTemplate> */
    private function selectableTemplates(): Builder
    {
        return WhatsappTemplate::query()
            ->where('kind', TemplateKind::MetaHsm->value)
            ->where('status', 'APPROVED')
            ->whereNotNull('whatsapp_instance_id')
            ->whereRaw("TRIM(meta_template_name) <> ''")
            ->whereRaw("TRIM(language) <> ''")
            ->whereRaw("TRIM(meta_waba_id) <> ''")
            ->whereHas('whatsappInstance', fn (Builder $query): Builder => $query
                ->whereRaw("TRIM(meta_waba_id) <> ''")
                ->whereColumn('whatsapp_instances.meta_waba_id', 'whatsapp_templates.meta_waba_id'));
    }
}
