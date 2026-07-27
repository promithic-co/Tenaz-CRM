<?php

namespace App\Policies;

use App\Models\ContactList;
use App\Models\User;

class ContactListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwnerOrAdmin();
    }

    public function view(User $user, ContactList $contactList): bool
    {
        return $this->authorizeFor($user, $contactList);
    }

    public function update(User $user, ContactList $contactList): bool
    {
        return $this->authorizeFor($user, $contactList);
    }

    public function delete(User $user, ContactList $contactList): bool
    {
        return $this->authorizeFor($user, $contactList);
    }

    /**
     * Dropping a contact into a list is open to every member of the tenant.
     *
     * Deliberately weaker than `update`: an atendente working the inbox needs to
     * file the person they are talking to, which is additive and reversible.
     * Renaming, refiltering, freezing or deleting the list itself stays with
     * owners and administrators.
     */
    public function addEntry(User $user, ContactList $contactList): bool
    {
        return (string) $contactList->tenant_id === (string) $user->tenantId;
    }

    private function authorizeFor(User $user, ContactList $contactList): bool
    {
        return (string) $contactList->tenant_id === (string) $user->tenantId
            && $user->isOwnerOrAdmin();
    }
}
