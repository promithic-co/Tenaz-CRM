<?php

namespace App\Observers;

use App\Models\StatusMachine;

/**
 * Invalidates the request-level container cache for a tenant's StatusMachine
 * whenever the record is persisted. This ensures Lead::canTransitionTo() always
 * reads fresh data after a pipeline mutation within the same request.
 */
class StatusMachineObserver
{
    /**
     * Flush the request-level cache after any save (create or update).
     */
    public function saved(StatusMachine $statusMachine): void
    {
        if ($statusMachine->tenant_id) {
            StatusMachine::flushCache((string) $statusMachine->tenant_id);
        }
    }

    /**
     * Flush the request-level cache after deletion.
     */
    public function deleted(StatusMachine $statusMachine): void
    {
        if ($statusMachine->tenant_id) {
            StatusMachine::flushCache((string) $statusMachine->tenant_id);
        }
    }
}
