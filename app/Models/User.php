<?php

namespace App\Models;

use App\Enums\TenantRole;
use App\Support\Database\TenantMembership;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    /** Memoized result of the first-tenant fallback below, including a "no tenant" outcome. */
    private ?string $memoizedFirstTenantId = null;

    private bool $firstTenantIdResolved = false;

    /**
     * Roles already resolved for this user, keyed by tenant id.
     *
     * @var array<string, ?TenantRole>
     */
    private array $memoizedRoles = [];

    /**
     * Drop both memos so the next read goes back to the database.
     *
     * Callers do not normally need this: every write through `tenants()` calls it
     * for them (see TenantMembership). It stays public as the escape hatch for a
     * pivot changed some other way — a raw query, or the Tenant side of the
     * relation — while this instance is still alive.
     */
    public function forgetTenantMemo(): void
    {
        $this->memoizedFirstTenantId = null;
        $this->firstTenantIdResolved = false;
        $this->memoizedRoles = [];
    }

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
        if ($this->firstTenantIdResolved) {
            return $this->memoizedFirstTenantId;
        }

        $firstTenant = $this->relationLoaded('tenants')
            ? $this->getRelation('tenants')->first()
            : $this->tenants()->first();

        $this->firstTenantIdResolved = true;

        if (! $firstTenant) {
            return $this->memoizedFirstTenantId = null;
        }

        // The row this query already carries answers roleFor() for the same
        // tenant, so seed that memo instead of paying a second select for it.
        $this->memoizedRoles[(string) $firstTenant->id] = TenantRole::tryFrom((string) $firstTenant->pivot->role);

        return $this->memoizedFirstTenantId = (string) $firstTenant->id;
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role')->withTimestamps();
    }

    /**
     * Route every belongsToMany on this model through TenantMembership so pivot
     * writes reset the memos above. `tenants()` is the only one.
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
    ): BelongsToMany {
        return new TenantMembership($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Resolve the authenticated user's role inside a given tenant.
     *
     * Every permission check funnels through here via currentRole(), so the
     * result is memoized per tenant id — including a "not a member" outcome,
     * which is what a super-admin acting as a tenant they do not belong to
     * gets on each of those checks.
     */
    public function roleFor(Tenant|int|string|null $tenant): ?TenantRole
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        if ($tenantId === null) {
            return null;
        }

        $tenantId = (string) $tenantId;

        if (array_key_exists($tenantId, $this->memoizedRoles)) {
            return $this->memoizedRoles[$tenantId];
        }

        $pivotRow = $this->tenants()
            ->where('tenants.id', $tenantId)
            ->first();

        return $this->memoizedRoles[$tenantId] = $pivotRow
            ? TenantRole::tryFrom((string) $pivotRow->pivot->role)
            : null;
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
