<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * Resolves and mutates the tenant a super-admin is currently acting as.
 *
 * The selection lives in the session (never in the URL) so the backoffice path
 * stays opaque. When no tenant is selected a super-admin keeps the historical
 * unscoped, cross-tenant view.
 */
class ActiveTenant
{
    public const SESSION_KEY = 'active_tenant_id';

    /**
     * The selected tenant id, or null when nothing is selected.
     *
     * Reads the session store directly so the value is available anywhere the
     * session is running — including inside the tenant global scope, which is
     * evaluated outside the controller layer. Outside an HTTP request (queue
     * workers, console) the session is never started and this returns null.
     */
    public function id(): ?string
    {
        if (! Session::isStarted()) {
            return null;
        }

        $tenantId = Session::get(self::SESSION_KEY);

        return is_scalar($tenantId) && (string) $tenantId !== ''
            ? (string) $tenantId
            : null;
    }

    public function tenant(): ?Tenant
    {
        $tenantId = $this->id();

        return $tenantId === null
            ? null
            : Tenant::query()->find($tenantId);
    }

    /**
     * Point a super-admin at a tenant. Returns false when the caller is not a
     * super-admin or the tenant does not exist — the session is left untouched.
     */
    public function selectForSuperAdmin(User $user, string $tenantId): bool
    {
        if (! $user->is_super_admin) {
            return false;
        }

        $selectedTenantId = Tenant::query()->whereKey($tenantId)->value('id');

        if ($selectedTenantId === null) {
            return false;
        }

        Session::put(self::SESSION_KEY, (string) $selectedTenantId);

        return true;
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Every tenant a super-admin may act as, for the switcher.
     *
     * @return list<array{id: string, name: string}>
     */
    public function availableForSuperAdmin(): array
    {
        return Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Tenant $tenant): array => [
                'id' => (string) $tenant->id,
                'name' => (string) $tenant->name,
            ])
            ->all();
    }
}
