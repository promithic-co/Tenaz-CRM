<?php

namespace App\Http\Controllers\Settings;

use App\Enums\TenantRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendInvitationRequest;
use App\Mail\TenantInvitationMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        $tenant = $this->resolveTenant();

        $members = $tenant->users()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => (string) $user->pivot->role,
                'role_label' => TenantRole::tryFrom((string) $user->pivot->role)?->label() ?? $user->pivot->role,
                'is_current_user' => $user->id === auth()->id(),
            ]);

        $invitations = $tenant->invitations()
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TenantInvitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'is_expired' => $invitation->isExpired(),
            ]);

        return Inertia::render('settings/Team', [
            'members' => $members,
            'invitations' => $invitations,
            'assignable_roles' => array_map(
                fn (TenantRole $r) => ['value' => $r->value, 'label' => $r->label()],
                TenantRole::assignable(),
            ),
        ]);
    }

    public function inviteStore(SendInvitationRequest $request): RedirectResponse
    {
        $tenant = $this->resolveTenant();

        $email = (string) $request->validated('email');

        if ($tenant->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Este usuário já faz parte da equipe.',
            ]);
        }

        $tokens = TenantInvitation::generateToken();

        $invitation = TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_user_id' => auth()->id(),
            'email' => $email,
            'role' => $request->validated('role'),
            'token' => $tokens['hashed'],
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new TenantInvitationMail($invitation->fresh(['tenant', 'invitedBy']), $tokens['plain']));

        return back()->with('success', 'Convite enviado.');
    }

    public function inviteDestroy(TenantInvitation $invitation): RedirectResponse
    {
        $this->ensureInvitationBelongsToTenant($invitation);
        $invitation->delete();

        return back()->with('success', 'Convite cancelado.');
    }

    public function memberUpdate(Request $request, User $user): RedirectResponse
    {
        $tenant = $this->resolveTenant();
        $this->ensureMemberBelongsToTenant($tenant, $user);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in([TenantRole::Administrator->value, TenantRole::User->value])],
        ]);

        $currentRole = TenantRole::tryFrom((string) $user->tenants()->where('tenants.id', $tenant->id)->first()?->pivot->role);

        if ($currentRole === TenantRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Não é possível alterar o perfil do proprietário.',
            ]);
        }

        $user->tenants()->updateExistingPivot($tenant->id, ['role' => $validated['role']]);

        return back()->with('success', 'Perfil atualizado.');
    }

    public function memberDestroy(User $user): RedirectResponse
    {
        $tenant = $this->resolveTenant();
        $this->ensureMemberBelongsToTenant($tenant, $user);

        $role = TenantRole::tryFrom((string) $user->tenants()->where('tenants.id', $tenant->id)->first()?->pivot->role);

        if ($role === TenantRole::Owner) {
            throw ValidationException::withMessages([
                'user' => 'Não é possível remover o proprietário da equipe.',
            ]);
        }

        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'user' => 'Você não pode remover a si mesmo.',
            ]);
        }

        $user->tenants()->detach($tenant->id);

        return back()->with('success', 'Membro removido.');
    }

    private function resolveTenant(): Tenant
    {
        $tenantId = auth()->user()->tenantId;

        abort_if(! $tenantId, 403);

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($tenantId);

        return $tenant;
    }

    private function ensureInvitationBelongsToTenant(TenantInvitation $invitation): void
    {
        abort_if($invitation->tenant_id !== (int) auth()->user()->tenantId, 404);
    }

    private function ensureMemberBelongsToTenant(Tenant $tenant, User $user): void
    {
        abort_if(! $tenant->users()->where('users.id', $user->id)->exists(), 404);
    }
}
