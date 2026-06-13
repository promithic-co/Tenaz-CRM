<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactListEntryRequest;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Services\ContactSyncService;
use Illuminate\Http\RedirectResponse;

class ContactListEntryController extends Controller
{
    public function __construct(private readonly ContactSyncService $contactSync) {}

    public function store(StoreContactListEntryRequest $request, ContactList $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $entry = ContactListEntry::firstOrCreate(
            ['contact_list_id' => $list->id, 'phone' => $request->validated('phone')],
            ['name' => $request->validated('name'), 'opt_in_status' => 'pending']
        );

        $this->contactSync->syncFromEntry($entry, Contact::SOURCE_MANUAL);

        $list->refreshEntriesCount();

        return back()->with('success', 'Contato adicionado.');
    }

    public function destroy(ContactListEntry $entry): RedirectResponse
    {
        $this->authorize('delete', $entry);

        $list = $entry->contactList;
        $entry->delete();

        if ($list) {
            $list->refreshEntriesCount();
        }

        return back()->with('success', 'Contato removido.');
    }
}
