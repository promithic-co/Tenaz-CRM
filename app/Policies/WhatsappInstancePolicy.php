<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappInstance;

class WhatsappInstancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappInstance $whatsappInstance): bool
    {
        return $this->authorizeFor($user, $whatsappInstance);
    }

    public function update(User $user, WhatsappInstance $whatsappInstance): bool
    {
        return $this->authorizeFor($user, $whatsappInstance);
    }

    public function delete(User $user, WhatsappInstance $whatsappInstance): bool
    {
        if ($whatsappInstance->tenant_id !== $user->tenantId) {
            return false;
        }

        return $user->isOwnerOrAdmin();
    }

    private function authorizeFor(User $user, WhatsappInstance $whatsappInstance): bool
    {
        if ((string) $whatsappInstance->tenant_id !== (string) $user->tenantId) {
            return false;
        }

        if ($user->isRestrictedUser()) {
            return $whatsappInstance->user_id === $user->id;
        }

        return true;
    }
}
