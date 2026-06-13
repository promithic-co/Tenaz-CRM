<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Allow only platform super-admins through.
     * Clears the active tenant context so cross-tenant queries are unscoped.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->is_super_admin) {
            abort(403);
        }

        $request->session()->forget('active_tenant_id');

        return $next($request);
    }
}
