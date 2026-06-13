<?php

namespace App\Actions\BulkLeadActions;

use App\Models\Lead;
use App\Services\ConversationAutomationService;
use App\Services\PauseService;
use Illuminate\Support\Facades\DB;

class PauseAiHandler implements BulkLeadActionHandler
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

        DB::transaction(function () use ($lead): void {
            $this->pause->pause((string) $lead->whatsapp, (string) $lead->tenant_id);
            $this->automation->pauseForHuman($lead, auth()->user(), 'bulk_pause');
        });
    }
}
