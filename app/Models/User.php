<?php

namespace App\Models;

use App\Enums\TenantRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
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

    /**
     * Memoized result of the first-tenant fallback below.
     *
     * Only ever holds a resolved id — a "no tenant" outcome is deliberately not
     * cached, so a tenant attached later in the same instance's lifetime (user
     * registration, invitation acceptance) is still picked up.
     */
    private ?string $memoizedFirstTenantId = null;

    /** The tenant identifier for this user (used to scope data in multi-tenant queries). */
    public function getTenantIdAttribute(): ?string
    {
        // Only check session in HTTP context where a session store is available.
        // Read every time rather than memoized so switching the active tenant
        // mid-request (backoffice switcher) takes effect immediately.
        if (request() && request()->hasSession()) {
            $activeTenant = request()->session()->get('active_tenant_id');
            if ($activeTenant) {
                return (string) $activeTenant;
            }
        }

        return $this->resolveFirstTenantId();
    }

    /**
     * The user's first tenant, resolved at most once per model instance.
     *
     * This accessor is read on every tenant-scoped query, so the uncached pivot
     * select dominated request query counts. Prefers an already eager-loaded
     * relation, then falls back to a single query whose result is memoized.
     */
    private function resolveFirstTenantId(): ?string
    {
        if ($this->memoizedFirstTenantId !== null) {
            return $this->memoizedFirstTenantId;
        }

        $firstTenant = $this->relationLoaded('tenants')
            ? $this->getRelation('tenants')->first()
            : $this->tenants()->first();

        return $this->memoizedFirstTenantId = $firstTenant ? (string) $firstTenant->id : null;
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
