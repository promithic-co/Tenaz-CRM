<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'currentRole' => fn () => $user?->currentRole()?->value,
                'is_super_admin' => (bool) $user?->is_super_admin,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'escalation_count' => fn () => auth()->check()
                ? \App\Models\ServiceTicket::where('tenant_id', auth()->user()->tenantId)
                    ->active()
                    ->count()
                : 0,
            'flash' => fn () => $request->session()->get('flash'),
            'flash_error' => fn () => $request->session()->get('flash_error'),
        ];
    }
}
