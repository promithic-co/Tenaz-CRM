<?php

namespace App\Models\Concerns;

use App\Services\ActiveTenant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        static::addGlobalScope('tenant', function (Builder $builder): void {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $column = $builder->qualifyColumn((new static)->getTenantColumn());

            if (! $user->is_super_admin) {
                $builder->where($column, $user->tenantId);

                return;
            }

            /**
             * A super-admin acting as a specific tenant is scoped to it, so the
             * whole app behaves exactly as that tenant sees it. With nothing
             * selected the historical unscoped, cross-tenant view is kept.
             */
            $activeTenantId = app(ActiveTenant::class)->id();

            if ($activeTenantId !== null) {
                $builder->where($column, $activeTenantId);
            }
        });
    }

    public function getTenantColumn(): string
    {
        return 'tenant_id';
    }
}
