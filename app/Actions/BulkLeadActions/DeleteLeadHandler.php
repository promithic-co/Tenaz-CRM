<?php

namespace App\Actions\BulkLeadActions;

use App\Models\Lead;

class DeleteLeadHandler implements BulkLeadActionHandler
{
    public function handle(Lead $lead): void
    {
        if (! auth()->user()->can('delete', $lead)) {
            throw new \DomainException('forbidden');
        }

        $lead->update([
            'followup_status' => 'inactive',
            'ai_mode' => Lead::AI_MODE_MANUAL,
        ]);
        $lead->delete();
    }
}
