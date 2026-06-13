<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->authorizeFor($user, $lead);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->authorizeFor($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        if ((string) $lead->tenant_id !== (string) $user->tenantId) {
            return false;
        }

        // Restricted users may triage (view/update) leads but never delete them;
        // destruction is reserved for privileged roles.
        if ($user->isRestrictedUser()) {
            return false;
        }

        return true;
    }

    private function authorizeFor(User $user, Lead $lead): bool
    {
        if ((string) $lead->tenant_id !== (string) $user->tenantId) {
            return false;
        }

        if ($user->isRestrictedUser()) {
            $lead->loadMissing('agent');

            return $lead->agent?->user_id === $user->id
                || (int) $lead->assigned_user_id === (int) $user->id
                // CRM triage queue: leads with no agent and no assignee are
                // visible to any tenant member so they can pick up new inbound.
                || ($lead->agent_id === null && $lead->assigned_user_id === null);
        }

        return true;
    }

    /**
     * Operator action: claim an unassigned conversation. Only allowed when the
     * lead is currently in the triage queue (no assignee).
     */
    /**
     * Any same-tenant member may attempt to claim. The lifecycle service
     * enforces the assignment conflict — it returns a ValidationException
     * if the ticket is already claimed by another user, which propagates
     * as a redirect with errors rather than a 403.
     */
    public function assume(User $user, Lead $lead): bool
    {
        return (string) $lead->tenant_id === (string) $user->tenantId;
    }

    /**
     * Playground action: a lead may be operated on in the sandbox only when it
     * is a sandbox lead belonging to the acting user's tenant. Cross-tenant
     * leads are already filtered by the Lead global tenant scope (404 at
     * route-model-binding); this guards the same-tenant non-sandbox case.
     */
    public function sandbox(User $user, Lead $lead): bool
    {
        return $lead->is_sandbox && (string) $lead->tenant_id === (string) $user->tenantId;
    }
}
