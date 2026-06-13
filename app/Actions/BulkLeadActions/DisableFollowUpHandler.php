<?php

namespace App\Actions\BulkLeadActions;

use App\Models\Lead;

class DisableFollowUpHandler implements BulkLeadActionHandler
{
    public function handle(Lead $lead): void
    {
        $lead->disableFollowUp();
    }
}
