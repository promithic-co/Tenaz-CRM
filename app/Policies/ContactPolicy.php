<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contact $contact): bool
    {
        if ((string) $contact->tenant_id !== (string) $user->tenantId) {
            return false;
        }

        if ($user->isRestrictedUser()) {
            // Restricted users may only view contacts through leads they are allowed to see.
            return Lead::query()
                ->where('contact_id', $contact->id)
                ->get()
                ->contains(fn (Lead $lead) => $user->can('view', $lead));
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->isOwnerOrAdmin();
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->ownerOrAdminTenantMatch($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->ownerOrAdminTenantMatch($user, $contact);
    }

    public function restore(User $user, Contact $contact): bool
    {
        return $this->ownerOrAdminTenantMatch($user, $contact);
    }

    public function forceDelete(User $user, Contact $contact): bool
    {
        return $this->ownerOrAdminTenantMatch($user, $contact);
    }

    private function ownerOrAdminTenantMatch(User $user, Contact $contact): bool
    {
        return (string) $contact->tenant_id === (string) $user->tenantId
            && $user->isOwnerOrAdmin();
    }
}
