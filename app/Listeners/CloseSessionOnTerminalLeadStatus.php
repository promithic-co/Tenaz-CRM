<?php

namespace App\Listeners;

use App\Events\LeadStatusChanged;
use App\Models\Lead;
use App\Services\ConversationSessionLifecycleService;

/**
 * Closes the lead's open ConversationSession when it reaches a terminal status.
 *
 * Terminal statuses end the sales cycle, so the atendimento is done: this stops the
 * funnel/metrics from counting it as live even when the conclusion happened outside
 * the ticket lifecycle (e.g. a direct status change). Idempotent — the lifecycle
 * service no-ops when there is no open session.
 */
class CloseSessionOnTerminalLeadStatus
{
    public function __construct(private readonly ConversationSessionLifecycleService $sessions) {}

    public function handle(LeadStatusChanged $event): void
    {
        $outcome = $this->sessions->outcomeForStatus($event->newStatus);

        if ($outcome === null) {
            return;
        }

        $lead = Lead::withoutGlobalScopes()->find($event->leadId);

        if ($lead === null) {
            return;
        }

        $this->sessions->closeOpenForLead($lead, $outcome);
    }
}
