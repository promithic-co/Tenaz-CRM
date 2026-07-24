<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Allow only platform super-admins through.
     *
     * The active tenant selection is deliberately preserved: the backoffice
     * lets a super-admin act as a specific company, and clearing it here would
     * drop that selection on every request.
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

        return $next($request);
    }
}
