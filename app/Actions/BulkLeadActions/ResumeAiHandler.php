<?php

namespace App\Actions\BulkLeadActions;

use App\Models\Lead;
use App\Services\ConversationAutomationService;
use App\Services\PauseService;

class ResumeAiHandler implements BulkLeadActionHandler
{
    public function __construct(
        private readonly PauseService $pause,
        private readonly ConversationAutomationService $automation,
    ) {}

    public function handle(Lead $lead): void
    {
        if ($lead->deleted_at !== null) {
            throw new \DomainException('lead_deleted');
        }

        $this->pause->resume((string) $lead->whatsapp, (string) $lead->tenant_id);
        $this->automation->resumeAi($lead);
    }
}
