<?php

namespace App\Http\Middleware;

use App\Models\ServiceTicket;
use App\Notifications\MetaQualityRedNotification;
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
            /**
             * The backoffice prefix is configurable per environment and must be
             * resolved at runtime, never baked into the JS bundle. Only exposed
             * to super-admins so the path stays opaque to everyone else.
             */
            'backoffice' => [
                'path' => $user?->is_super_admin ? config('backoffice.path') : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'escalation_count' => fn () => auth()->check()
                ? ServiceTicket::where('tenant_id', auth()->user()->tenantId)
                    ->active()
                    ->count()
                : 0,
            'critical_notification_count' => fn () => $user
                ? $user->unreadNotifications()
                    ->where('type', MetaQualityRedNotification::class)
                    ->count()
                : 0,
            'critical_notifications' => fn () => $user
                ? $user->unreadNotifications()
                    ->where('type', MetaQualityRedNotification::class)
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn ($notification): array => [
                        'id' => $notification->id,
                        'title' => $notification->data['title'] ?? 'Alerta critico',
                        'body' => $notification->data['body'] ?? '',
                        'campaign_id' => $notification->data['campaign_id'] ?? null,
                        'campaign_name' => $notification->data['campaign_name'] ?? null,
                        'action_url' => $notification->data['action_url'] ?? null,
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ])
                    ->values()
                : [],
            'flash' => fn () => $request->session()->get('flash'),
            'flash_error' => fn () => $request->session()->get('flash_error'),
        ];
    }
}
