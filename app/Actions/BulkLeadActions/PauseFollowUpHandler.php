<?php

namespace App\Actions\BulkLeadActions;

use App\Models\Lead;

class PauseFollowUpHandler implements BulkLeadActionHandler
{
    public function handle(Lead $lead): void
    {
        if ($lead->followup_status !== 'active') {
            throw new \DomainException('not_active');
        }

        $lead->pauseFollowUp();
    }
}
