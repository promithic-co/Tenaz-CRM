<?php

namespace App\Actions\BulkLeadActions;

use App\Models\Lead;

class ResumeFollowUpHandler implements BulkLeadActionHandler
{
    public function handle(Lead $lead): void
    {
        if ($lead->followup_status !== 'paused') {
            throw new \DomainException('not_paused');
        }

        $lead->resumeFollowUp();
    }
}
