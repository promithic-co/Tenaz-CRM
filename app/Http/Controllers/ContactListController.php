<?php

namespace App\Http\Controllers;

use App\Actions\ImportContactListCsvAction;
use App\Http\Requests\ImportContactListCsvRequest;
use App\Http\Requests\SmartList\PreviewSmartListRequest;
use App\Http\Requests\SmartList\StoreDynamicContactListRequest;
use App\Http\Requests\SmartList\UpdateFiltersRequest;
use App\Models\Agent;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\WhatsappInstance;
use App\Services\SmartList\SmartListResolverService;
use App\Support\ContactListFilterChipPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactListController extends Controller
{
    public function __construct(
        protected SmartListResolverService $resolver,
    ) {}

    public function index(): Response
    {
        $lists = ContactList::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('listas-contato/Index', [
            'lists' => $lists,
            'can' => [],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('listas-contato/Create', $this->formOptions(auth()->user()->tenantId));
    }

    /**
     * Shared status/agent/instance option lists used by the create and show forms.
     *
     * @return array{statuses: array<int, array{value: string, label: string}>, agents: array<int, array<string, mixed>>, instances: array<int, array<string, mixed>>}
     */
    private function formOptions(string $tenantId): array
    {
        $statuses = [
            ['value' => 'novo', 'label' => 'Novo'],
            ['value' => 'qualificado', 'label' => 'Qualificado'],
            ['value' => 'sem_credito', 'label' => 'Sem Crédito'],
            ['value' => 'desqualificado', 'label' => 'Desqualificado'],
            ['value' => 'escalado', 'label' => 'Escalado'],
            ['value' => 'convertido', 'label' => 'Convertido'],
            ['value' => 'optou_sair', 'label' => 'Optou por Sair'],
        ];

        $agents = Agent::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name as nome'])
            ->toArray();

        $instances = WhatsappInstance::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('display_name')
            ->get(['id', 'display_name as label'])
            ->toArray();

        return compact('statuses', 'agents', 'instances');
    }

    public function store(StoreDynamicContactListRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = auth()->user()->tenantId;
        $data['source'] = 'manual';

        $list = ContactList::create($data);

        // D-02: dynamic → show (resolve to see leads), static → index
        if ($list->is_dynamic) {
            return redirect()
                ->route('listas-contato.show', $list)
                ->with('success', 'Lista dinâmica criada. Resolva pra ver os leads.');
        }

        return redirect()
            ->route('listas-contato.index')
            ->with('success', 'Lista estática criada.');
    }

    public function show(ContactList $list): Response
    {
        $this->authorize('view', $list);

        $entries = $list->entries()
            ->with('lead:id,nome')
            ->orderByDesc('created_at')
            ->paginate(25);

        $hasCampaignInSending = $list->campaigns()->where('status', 'sending')->exists();
        $filterChips = ContactListFilterChipPresenter::present($list->filters_json);

        return Inertia::render('listas-contato/Show', [
            'list' => array_merge($list->toArray(), ['has_campaign_in_sending' => $hasCampaignInSending]),
            'entries' => $entries,
            'optInStats' => [
                'pending' => $list->entries()->where('opt_in_status', 'pending')->count(),
                'opted_in' => $list->entries()->where('opt_in_status', 'opted_in')->count(),
                'opted_out' => $list->entries()->where('opt_in_status', 'opted_out')->count(),
            ],
            'filterChips' => $filterChips,
            ...$this->formOptions(auth()->user()->tenantId),
        ]);
    }

    public function destroy(ContactList $list): RedirectResponse
    {
        $this->authorize('delete', $list);

        $hasActiveCampaigns = Campaign::where('contact_list_id', $list->id)
            ->whereIn('status', ['sending', 'paused'])
            ->exists();

        if ($hasActiveCampaigns) {
            return back()->withErrors(['list' => 'Não é possível remover uma lista com campanhas ativas.']);
        }

        $list->delete();

        return redirect()->route('listas-contato.index')
            ->with('success', 'Lista removida.');
    }

    /**
     * D-07: Preview leads matching filters — returns {count, capped, sample}.
     * capped=true when matching leads exceed 5000 (LIMIT 5001 query).
     * sample is always limited to 10 and never includes whatsapp field (LGPD).
     */
    public function preview(PreviewSmartListRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenantId;
        $filters = $request->validated()['filters_json'];

        // D-07: capped count returns {count, capped} — FilterPreview.vue renders "5000+" when capped.
        $countResult = $this->resolver->countCapped($tenantId, $filters);
        $sample = $this->resolver->preview($tenantId, $filters, limit: 10);

        return response()->json([
            'count' => $countResult['count'],
            'capped' => $countResult['capped'],
            'sample' => $sample->map(fn ($lead) => [
                'id' => $lead->id,
                'nome' => $lead->nome,
                'status' => $lead->status,
                'tags' => $lead->tags->map(fn ($t) => [
                    'name' => $t->name,
                    'color' => $t->color,
                    'is_hot' => $t->is_hot,
                ])->all(),
            ])->all(),
        ]);
    }

    /**
     * Update filters on a dynamic list.
     * D-14: blocked when a campaign using this list is actively sending.
     */
    public function updateFilters(UpdateFiltersRequest $request, ContactList $list): RedirectResponse
    {
        $list->update(['filters_json' => $request->validated()['filters_json']]);

        return back()->with('success', 'Filtros salvos. Próximo dispatch usará as novas regras.');
    }

    /**
     * Materialize a dynamic list on demand (Index row action + Show.vue refresh).
     */
    public function refresh(ContactList $list): RedirectResponse
    {
        $this->authorize('update', $list);
        abort_unless($list->is_dynamic, 422, 'Lista estática não tem refresh.');

        $count = $this->resolver->materialize($list);

        return back()->with('success', "Lista atualizada — $count leads.");
    }

    /**
     * Freeze a dynamic list into a static snapshot.
     * Show.vue "Congelar lista" only — D-15: not exposed from Index row actions.
     */
    public function freeze(ContactList $list): RedirectResponse
    {
        $this->authorize('update', $list);
        abort_unless($list->is_dynamic, 422, 'Lista já é estática.');

        $count = $this->resolver->materialize($list);
        $list->update(['is_dynamic' => false]);

        return back()->with('success', "Lista congelada — $count leads no snapshot.");
    }

    public function importCsv(ImportContactListCsvRequest $request, ContactList $list): RedirectResponse
    {
        $this->authorize('update', $list);

        return app(ImportContactListCsvAction::class)->execute($list, $request->file('file'));
    }
}
