<?php

namespace App\Http\Middleware;

use App\Services\ActiveTenant;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the company switcher state with the backoffice pages and drops a
 * selection that points at a tenant which no longer exists.
 */
class ShareBackofficeContext
{
    public function __construct(private ActiveTenant $activeTenant) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->activeTenant->tenant();

        if ($tenant === null && $this->activeTenant->id() !== null) {
            $this->activeTenant->clear();
        }

        Inertia::share([
            'backoffice' => [
                'path' => config('backoffice.path'),
                'active_tenant' => $tenant === null ? null : [
                    'id' => (string) $tenant->id,
                    'name' => (string) $tenant->name,
                ],
                'tenants' => fn (): array => $this->activeTenant->availableForSuperAdmin(),
            ],
        ]);

        return $next($request);
    }
}
