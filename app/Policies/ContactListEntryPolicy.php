<?php

namespace App\Policies;

use App\Models\ContactListEntry;
use App\Models\User;

class ContactListEntryPolicy
{
    public function delete(User $user, ContactListEntry $entry): bool
    {
        return (string) $entry->contactList?->tenant_id === (string) $user->tenantId
            && $user->isOwnerOrAdmin();
    }
}
