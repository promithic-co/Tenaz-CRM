<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        if (! $invitation) {
            return redirect()->route('login')
                ->withErrors(['invitation' => 'Este convite é inválido ou já expirou.']);
        }

        $invitation->loadMissing('tenant', 'invitedBy');

        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'tenant_name' => $invitation->tenant?->name,
                'invited_by' => $invitation->invitedBy?->name,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ],
            'existing_user' => User::where('email', $invitation->email)->exists(),
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        if (! $invitation) {
            return redirect()->route('login')
                ->withErrors(['invitation' => 'Este convite é inválido ou já expirou.']);
        }

        $user = DB::transaction(function () use ($invitation, $request) {
            $user = User::firstOrNew(['email' => $invitation->email]);

            if (! $user->exists) {
                $user->name = $request->string('name');
                $user->password = $request->string('password');
                $user->save();
            }

            $user->tenants()->syncWithoutDetaching([
                $invitation->tenant_id => ['role' => $invitation->role->value],
            ]);

            $invitation->markAccepted();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('active_tenant_id', $invitation->tenant_id);

        return redirect()->route('dashboard')
            ->with('success', 'Convite aceito. Bem-vindo(a)!');
    }

    private function findValidInvitation(string $plainToken): ?TenantInvitation
    {
        $invitation = TenantInvitation::where('token', TenantInvitation::hashToken($plainToken))->first();

        if (! $invitation || ! $invitation->isPending()) {
            return null;
        }

        return $invitation;
    }
}
