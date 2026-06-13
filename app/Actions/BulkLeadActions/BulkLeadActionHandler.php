<?php

namespace App\Actions\BulkLeadActions;

use App\Models\Lead;

/**
 * One operator action applied to a single lead during a bulk operation.
 *
 * A handler MUST throw on any guard failure so the caller can tally it as a
 * skip; the thrown exception's message is the guard tag (e.g. 'lead_deleted',
 * 'not_active'). Handlers do not record audit events — the caller wraps the
 * successful application with the shared `lead_bulk_action` event.
 */
interface BulkLeadActionHandler
{
    public function handle(Lead $lead): void;
}
