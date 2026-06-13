<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddContactsToListRequest;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Services\ContactSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(private readonly ContactSyncService $contactSync) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contact::class);

        $user = $request->user();
        $tenantId = (string) $user->tenantId;

        $query = Contact::query()
            ->forTenant($tenantId)
            ->search((string) $request->query('q', ''));

        if ($status = $request->query('status')) {
            $query->where('opt_in_status', $status);
        }

        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }

        // Restricted users only see contacts linked to leads they can access.
        if ($user->isRestrictedUser()) {
            $query->whereHas('leads', function ($q) use ($user): void {
                $q->where(function ($inner) use ($user): void {
                    $inner->whereHas('agent', fn ($a) => $a->where('user_id', $user->id))
                        ->orWhere('assigned_user_id', $user->id);
                });
            });
        }

        $contacts = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('contatos/Index', [
            'contacts' => $contacts,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => $request->query('status'),
                'source' => $request->query('source'),
            ],
            'lists' => ContactList::query()
                ->forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'can' => [
                'manage' => $user->isOwnerOrAdmin(),
            ],
        ]);
    }

    /**
     * Lightweight JSON search for the "Adicionar contatos existentes" picker on the
     * contact-list show page. Returns up to 20 matches and flags entries already in
     * the target list so the UI can disable them.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $tenantId = (string) $request->user()->tenantId;
        $term = (string) $request->query('q', '');
        $listId = $request->integer('list_id') ?: null;

        $matches = Contact::query()
            ->forTenant($tenantId)
            ->search($term)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'email', 'cpf', 'opt_in_status']);

        $alreadyInList = [];
        if ($listId) {
            $alreadyInList = ContactListEntry::query()
                ->where('contact_list_id', $listId)
                ->whereIn('contact_id', $matches->pluck('id'))
                ->pluck('contact_id')
                ->all();
        }

        return response()->json([
            'contacts' => $matches,
            'already_in_list' => $alreadyInList,
        ]);
    }

    public function show(Contact $contact): Response
    {
        $this->authorize('view', $contact);

        $contact->load([
            'leads' => fn ($q) => $q->select('id', 'contact_id', 'nome', 'whatsapp', 'status', 'operational_stage', 'agent_id', 'evolution_instance', 'service_window_expires_at', 'free_entry_point_expires_at', 'conversation_window_source', 'updated_at'),
            'leads.tags:id,name,color,slug,is_hot',
            'contactListEntries.contactList:id,name,tenant_id',
        ]);

        $contact->setRelation('leads', $contact->leads->sortByDesc('updated_at')->values());

        $latestLead = $contact->leads->first();
        $conversationWindow = $latestLead
            ? app(\App\Services\WhatsApp\WhatsAppConversationWindowResolver::class)->resolve($latestLead->loadMissing('whatsappInstance'))
            : null;

        return Inertia::render('contatos/Show', [
            'contact' => $contact,
            'leads' => $contact->leads,
            'listMemberships' => $contact->contactListEntries,
            'conversationWindow' => $conversationWindow,
            'can' => [
                'manage' => auth()->user()?->isOwnerOrAdmin() ?? false,
            ],
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $contact = $this->contactSync->resolveContact(
            tenantId: (string) $user->tenantId,
            rawPhone: $data['phone'],
            attrs: [
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'cpf' => $data['cpf'] ?? null,
                'extra_data' => $data['extra_data'] ?? null,
                'opt_in_status' => $data['opt_in_status'] ?? Contact::OPT_PENDING,
            ],
            source: $data['source'] ?? Contact::SOURCE_MANUAL,
        );

        if (! $contact) {
            return back()->withErrors(['phone' => 'Telefone inválido.']);
        }

        return redirect()->route('contatos.show', $contact)
            ->with('success', 'Contato criado com sucesso.');
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        return redirect()->route('contatos.show', $contact)
            ->with('success', 'Contato atualizado.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()->route('contatos.index')
            ->with('success', 'Contato arquivado.');
    }

    /**
     * Add canonical contacts to a custom contact list as ContactListEntry rows,
     * skipping duplicates by [contact_list_id, phone].
     */
    public function addToList(AddContactsToListRequest $request, ContactList $list): RedirectResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $contactIds = array_values(array_unique($request->validated('contact_ids')));

        $contacts = Contact::query()
            ->forTenant($tenantId)
            ->whereIn('id', $contactIds)
            ->get(['id', 'phone', 'name', 'opt_in_status', 'opt_in_at', 'extra_data']);

        $existingEntries = ContactListEntry::query()
            ->where('contact_list_id', $list->id)
            ->whereIn('phone', $contacts->pluck('phone'))
            ->get()
            ->keyBy('phone');

        $added = 0;
        $skipped = 0;
        $newEntries = [];
        $now = now();

        foreach ($contacts as $contact) {
            $existing = $existingEntries->get($contact->phone);

            if ($existing) {
                if ($existing->contact_id === null) {
                    $existing->update(['contact_id' => $contact->id]);
                }
                $skipped++;

                continue;
            }

            $newEntries[] = [
                'contact_list_id' => $list->id,
                'contact_id' => $contact->id,
                'phone' => $contact->phone,
                'name' => $contact->name,
                'opt_in_status' => $contact->opt_in_status,
                'opt_in_at' => $contact->opt_in_at,
                'extra_data' => $contact->extra_data === null ? null : json_encode($contact->extra_data),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $added++;
        }

        if ($newEntries !== []) {
            ContactListEntry::query()->insert($newEntries);
        }

        $list->refreshEntriesCount();

        return back()->with('success', "Contatos adicionados: {$added}. Ignorados: {$skipped}.");
    }
}
