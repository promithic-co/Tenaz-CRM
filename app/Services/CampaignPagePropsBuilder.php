<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignPagePropsBuilder
{
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
            'templates' => WhatsappTemplate::query()
                ->where('status', 'APPROVED')
                ->with('whatsappInstance')
                ->get(['id', 'name', 'kind', 'element_name', 'variables_count', 'whatsapp_instance_id']),
            'instances' => WhatsappInstance::query()->get(['id', 'name', 'display_name', 'provider']),
            'contactListFilters' => Inertia::defer(fn () => ContactList::query()
                ->whereNotNull('filters_json')
                ->pluck('filters_json', 'id')),
            'templateBodies' => Inertia::defer(fn () => WhatsappTemplate::query()
                ->where('status', 'APPROVED')
                ->whereNotNull('body')
                ->pluck('body', 'id')),
            'defaults' => [
                'contact_list_id' => $request->integer('contact_list_id') ?: null,
                'whatsapp_instance_id' => $request->integer('whatsapp_instance_id') ?: null,
            ],
        ];
    }

    /**
     * @return array{campaign: Campaign, messages: mixed, repliedCount: int}
     */
    public function show(Campaign $campaign, Request $request): array
    {
        $campaign->load([
            'contactList:id,name',
            'whatsappTemplate:id,name,body,variables_count',
            'whatsappInstance:id,name,display_name,meta_quality_rating',
        ]);

        $messagesQuery = $campaign->messages()->with(['contactListEntry:id,name,phone']);

        if ($request->input('status')) {
            $messagesQuery->where('status', $request->input('status'));
        }

        return [
            'campaign' => $campaign,
            'messages' => $messagesQuery->orderByDesc('sent_at')->paginate(25),
            'repliedCount' => Lead::where('campaign_id', $campaign->id)->count(),
        ];
    }
}
