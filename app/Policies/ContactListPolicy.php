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

    private function authorizeFor(User $user, ContactList $contactList): bool
    {
        return (string) $contactList->tenant_id === (string) $user->tenantId
            && $user->isOwnerOrAdmin();
    }
}
