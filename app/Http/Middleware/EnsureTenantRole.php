<?php

namespace App\Http\Middleware;

use App\Enums\TenantRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantRole
{
    /**
     * Allow only authenticated users whose role in the active tenant matches
     * one of the given role values. Usage: ->middleware('role:owner,administrator').
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $currentRole = $user->currentRole();

        if (! $currentRole) {
            abort(403);
        }

        $allowed = array_map(
            fn (string $role) => TenantRole::tryFrom($role),
            $roles,
        );

        if (! in_array($currentRole, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
