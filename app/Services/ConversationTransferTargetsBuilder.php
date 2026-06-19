<?php

namespace App\Services;

use App\Models\User;

class ConversationTransferTargetsBuilder
{
    /**
     * @return list<array{type: string, id: int, name: string}>
     */
    public function forTenant(string $tenantId, User $actor): array
    {
        if (! $actor->isOwnerOrAdmin()) {
            return [];
        }

        return User::query()
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['type' => 'user', 'id' => $user->id, 'name' => $user->name])
            ->all();
    }
}
