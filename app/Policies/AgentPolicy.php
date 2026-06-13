<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Agent $agent): bool
    {
        return $this->authorizeFor($user, $agent);
    }

    public function update(User $user, Agent $agent): bool
    {
        return $this->authorizeFor($user, $agent);
    }

    public function manage(User $user, Agent $agent): bool
    {
        return $this->authorizeFor($user, $agent);
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $this->authorizeFor($user, $agent);
    }

    private function authorizeFor(User $user, Agent $agent): bool
    {
        if ((string) $agent->tenant_id !== (string) $user->tenantId) {
            return false;
        }

        if ($user->isRestrictedUser()) {
            return $agent->user_id === $user->id;
        }

        return true;
    }
}
