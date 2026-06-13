<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoiceCampaign;

class VoiceCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwnerOrAdmin();
    }

    public function view(User $user, VoiceCampaign $voiceCampaign): bool
    {
        return $this->authorizeFor($user, $voiceCampaign);
    }

    public function update(User $user, VoiceCampaign $voiceCampaign): bool
    {
        return $this->authorizeFor($user, $voiceCampaign);
    }

    public function delete(User $user, VoiceCampaign $voiceCampaign): bool
    {
        return $this->authorizeFor($user, $voiceCampaign);
    }

    private function authorizeFor(User $user, VoiceCampaign $voiceCampaign): bool
    {
        return (string) $voiceCampaign->tenant_id === (string) $user->tenantId
            && $user->isOwnerOrAdmin();
    }
}
