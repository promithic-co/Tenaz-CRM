<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoiceInstance;

class VoiceInstancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwnerOrAdmin();
    }

    public function view(User $user, VoiceInstance $voiceInstance): bool
    {
        return $this->authorizeFor($user, $voiceInstance);
    }

    public function update(User $user, VoiceInstance $voiceInstance): bool
    {
        return $this->authorizeFor($user, $voiceInstance);
    }

    public function delete(User $user, VoiceInstance $voiceInstance): bool
    {
        return $this->authorizeFor($user, $voiceInstance);
    }

    private function authorizeFor(User $user, VoiceInstance $voiceInstance): bool
    {
        return (string) $voiceInstance->tenant_id === (string) $user->tenantId
            && $user->isOwnerOrAdmin();
    }
}
