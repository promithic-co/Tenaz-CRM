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
     * Bypasses: every request when onboarding is disabled (config
     * onboarding.enabled = false), super-admins, invited administrators,
     * regular users, and owners who have already completed onboarding
     * (onboarded_at is set).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('onboarding.enabled', true)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && ! $user->is_super_admin && $user->isOwner() && $user->onboarded_at === null) {
            return redirect('/onboarding');
        }

        return $next($request);
    }
}
