<?php

namespace App\Actions\BulkLeadActions;

use App\Models\Lead;
use App\Services\AgentInteractionEventService;

/**
 * Keyed bulk-action strategy. Resolves the action key to a handler, applies it,
 * and records the shared `lead_bulk_action` audit event on success.
 *
 * An unknown key throws 'unknown_action'; a handler guard failure propagates so
 * the caller (LeadManagementController::bulkAction) tallies it as a skip.
 */
class ApplyBulkLeadAction
{
    public function __construct(
        private readonly AgentInteractionEventService $events,
    ) {}

    /**
     * @var array<string, class-string<BulkLeadActionHandler>>
     */
    private const HANDLERS = [
        'pause-ai' => PauseAiHandler::class,
        'resume-ai' => ResumeAiHandler::class,
        'pause-followup' => PauseFollowUpHandler::class,
        'resume-followup' => ResumeFollowUpHandler::class,
        'disable-followup' => DisableFollowUpHandler::class,
        'delete' => DeleteLeadHandler::class,
    ];

    public function execute(Lead $lead, string $action, int $userId): void
    {
        $handlerClass = self::HANDLERS[$action] ?? null;

        if ($handlerClass === null) {
            throw new \DomainException('unknown_action');
        }

        $interactionId = $this->events->newInteractionId();

        app($handlerClass)->handle($lead);

        $this->events->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'lead_bulk_action',
            eventSource: 'lead_management_controller',
            payload: [
                'action' => $action,
                'user_id' => $userId,
            ],
        );
    }
}
