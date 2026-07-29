<?php

namespace App\Support\Database;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The User → Tenant relation, which drops the parent's tenancy memos on every
 * pivot write.
 *
 * User memoizes both the resolved tenant id and the role per tenant, and those
 * memos live as long as the model instance rather than the request. Leaving
 * invalidation to each caller does not hold: the argument to a pivot write is
 * frequently `$user->tenantId` itself, which warms the memo immediately before
 * the row it came from changes. A stale tenant id scopes queries to a tenant the
 * user has left; a stale role makes a permission check answer with the role the
 * user had before the change.
 *
 * Every pivot write funnels through the four methods below — `toggle()`,
 * `syncWithoutDetaching()` and the `*OrFail()` variants all delegate to them — so
 * this is the one place that sees them all.
 */
class TenantMembership extends BelongsToMany
{
    /** @param  mixed  $ids */
    public function attach($ids, array $attributes = [], $touch = true)
    {
        $result = parent::attach($ids, $attributes, $touch);

        $this->forgetParentTenantMemo();

        return $result;
    }

    /** @param  mixed  $ids */
    public function detach($ids = null, $touch = true)
    {
        $result = parent::detach($ids, $touch);

        $this->forgetParentTenantMemo();

        return $result;
    }

    /**
     * @param  mixed  $ids
     * @return array{attached: array, detached: array, updated: array}
     */
    public function sync($ids, $detaching = true)
    {
        $result = parent::sync($ids, $detaching);

        $this->forgetParentTenantMemo();

        return $result;
    }

    /** @param  mixed  $id */
    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        $result = parent::updateExistingPivot($id, $attributes, $touch);

        $this->forgetParentTenantMemo();

        return $result;
    }

    private function forgetParentTenantMemo(): void
    {
        if ($this->parent instanceof User) {
            $this->parent->forgetTenantMemo();
        }
    }
}
