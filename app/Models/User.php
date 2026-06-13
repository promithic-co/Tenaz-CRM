<?php

namespace App\Models;

use App\Enums\TenantRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'onboarded_at' => 'datetime',
            'onboarding_whatsapp_skipped_at' => 'datetime',
        ];
    }

    /**
     * The draft agent being built during the onboarding wizard.
     * Set only by trusted onboarding controller code — not mass assignable.
     */
    public function onboardingAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'onboarding_agent_id');
    }

    /** The tenant identifier for this user (used to scope data in multi-tenant queries). */
    public function getTenantIdAttribute(): ?string
    {
        // Only check session in HTTP context where a session store is available
        if (request() && request()->hasSession()) {
            $activeTenant = request()->session()->get('active_tenant_id');
            if ($activeTenant) {
                return (string) $activeTenant;
            }
        }

        $firstTenant = $this->tenants()->first();

        return $firstTenant ? (string) $firstTenant->id : null;
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role')->withTimestamps();
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /** Resolve the authenticated user's role inside a given tenant. */
    public function roleFor(Tenant|int|string|null $tenant): ?TenantRole
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        if ($tenantId === null) {
            return null;
        }

        $pivotRow = $this->tenants()
            ->where('tenants.id', $tenantId)
            ->first();

        if (! $pivotRow) {
            return null;
        }

        return TenantRole::tryFrom((string) $pivotRow->pivot->role);
    }

    /** Role inside the currently-active tenant (session-selected). */
    public function currentRole(): ?TenantRole
    {
        return $this->roleFor($this->tenantId);
    }

    public function isOwner(): bool
    {
        return $this->currentRole() === TenantRole::Owner;
    }

    public function isAdministrator(): bool
    {
        return $this->currentRole() === TenantRole::Administrator;
    }

    public function isOwnerOrAdmin(): bool
    {
        return $this->currentRole()?->isPrivileged() === true;
    }

    /** True when the user has the lowest-privilege role inside the current tenant. */
    public function isRestrictedUser(): bool
    {
        return $this->currentRole() === TenantRole::User;
    }
}
