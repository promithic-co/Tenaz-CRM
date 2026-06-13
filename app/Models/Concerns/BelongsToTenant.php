<?php

namespace App\Models\Concerns;

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
            if ($user && ! $user->is_super_admin) {
                $column = (new static)->getTenantColumn();
                $builder->where($builder->qualifyColumn($column), $user->tenantId);
            }
        });
    }

    public function getTenantColumn(): string
    {
        return 'tenant_id';
    }
}
