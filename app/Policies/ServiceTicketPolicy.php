<?php

namespace App\Policies;

use App\Models\ServiceTicket;
use App\Models\User;

class ServiceTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceTicket $ticket): bool
    {
        return $this->authorizeFor($user, $ticket);
    }

    public function update(User $user, ServiceTicket $ticket): bool
    {
        return $this->authorizeFor($user, $ticket);
    }

    private function authorizeFor(User $user, ServiceTicket $ticket): bool
    {
        if ((string) $ticket->tenant_id !== (string) $user->tenantId) {
            return false;
        }

        if (! $user->isRestrictedUser()) {
            return true;
        }

        // Any same-tenant user may act on escalation tickets.
        // The lifecycle service enforces business rules (already-claimed, etc.).
        if ($ticket->type === ServiceTicket::TYPE_ESCALATION) {
            return true;
        }

        $ticket->loadMissing('lead.agent');

        if ((int) $ticket->assigned_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $ticket->lead?->assigned_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $ticket->lead?->agent?->user_id === (int) $user->id) {
            return true;
        }

        return false;
    }
}
