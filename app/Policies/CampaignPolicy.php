<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwnerOrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isOwnerOrAdmin();
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $this->authorizeFor($user, $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->authorizeFor($user, $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->authorizeFor($user, $campaign);
    }

    private function authorizeFor(User $user, Campaign $campaign): bool
    {
        return (string) $campaign->tenant_id === (string) $user->tenantId
            && $user->isOwnerOrAdmin();
    }
}
