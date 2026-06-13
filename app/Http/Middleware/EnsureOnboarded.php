<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    /**
     * Handle an incoming request.
     *
     * Redirects incomplete tenant owners to /onboarding.
     * Bypasses: super-admins, invited administrators, regular users,
     * and owners who have already completed onboarding (onboarded_at is set).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_super_admin && $user->isOwner() && $user->onboarded_at === null) {
            return redirect('/onboarding');
        }

        return $next($request);
    }
}
