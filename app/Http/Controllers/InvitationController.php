<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($invitation->email)])
            ->first();

        if ($existingUser && ! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        if ($existingUser && ! Auth::user()?->is($existingUser)) {
            return redirect()->route('dashboard')
                ->withErrors(['invitation' => 'Este convite pertence a outra conta.']);
        }

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
            'existing_user' => $existingUser !== null,
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        if (! $invitation) {
            return redirect()->route('login')
                ->withErrors(['invitation' => 'Este convite é inválido ou já expirou.']);
        }

        [$user, $tenantId] = DB::transaction(function () use ($invitation, $request): array {
            $lockedInvitation = TenantInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedInvitation || ! $lockedInvitation->isPending()) {
                throw ValidationException::withMessages([
                    'invitation' => 'Este convite é inválido ou já expirou.',
                ]);
            }

            $email = Str::lower($lockedInvitation->email);
            $password = (string) $request->input('password');

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if ($user) {
                if (! Hash::check($password, $user->password)) {
                    throw ValidationException::withMessages([
                        'password' => 'A senha atual está incorreta.',
                    ]);
                }
            } else {
                $user = User::create([
                    'name' => (string) $request->string('name'),
                    'email' => $email,
                    'password' => $password,
                ]);
            }

            $user->tenants()->syncWithoutDetaching([
                $lockedInvitation->tenant_id => ['role' => $lockedInvitation->role->value],
            ]);

            $lockedInvitation->markAccepted();

            return [$user, $lockedInvitation->tenant_id];
        });

        if (! Auth::check()) {
            Auth::login($user);
        }

        $request->session()->regenerate();
        $request->session()->put('active_tenant_id', $tenantId);

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
