<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class RoleScope
{
    /**
     * Restrict a query to rows owned by the authenticated user when that user has
     * the lowest-privilege role inside the tenant. Owners and administrators see
     * the full tenant-scoped dataset.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyOwnerScope(Builder $query, string $column = 'user_id'): Builder
    {
        $user = auth()->user();

        if ($user && $user->isRestrictedUser()) {
            $query->where($query->qualifyColumn($column), $user->id);
        }

        return $query;
    }
}
