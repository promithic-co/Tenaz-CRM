<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappTemplate;

class WhatsappTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwnerOrAdmin();
    }

    public function view(User $user, WhatsappTemplate $template): bool
    {
        return $this->authorizeFor($user, $template);
    }

    public function update(User $user, WhatsappTemplate $template): bool
    {
        return $this->authorizeFor($user, $template);
    }

    public function delete(User $user, WhatsappTemplate $template): bool
    {
        return $this->authorizeFor($user, $template);
    }

    private function authorizeFor(User $user, WhatsappTemplate $template): bool
    {
        return (string) $template->tenant_id === (string) $user->tenantId
            && $user->isOwnerOrAdmin();
    }
}
