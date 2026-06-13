<?php

namespace App\Listeners;

use App\Events\LeadStatusChanged;
use App\Jobs\TagLeadFromConversationJob;
use App\Models\AppSetting;
use App\Models\Tag;

/**
 * Dispatches auto-tag evaluation when a Lead transitions to a commercially-significant status.
 *
 * Trigger whitelist (D-05): only these statuses carry enough conversation signal to
 * warrant an AI scan. Other transitions may be re-evaluated manually.
 */
class DispatchAutoTagOnLeadStatusChanged
{
    /**
     * Status values that automatically trigger an AI tag evaluation.
     *
     * @var array<int, string>
     */
    private const TRIGGER_WHITELIST = [
        'qualificado',
        'escalado',
        'sem_credito',
        'desqualificado',
        'optou_sair',
    ];

    public function handle(LeadStatusChanged $event): void
    {
        // Only trigger for whitelisted status transitions
        if (! in_array($event->newStatus, self::TRIGGER_WHITELIST, true)) {
            return;
        }

        // Guard: feature must be enabled for the tenant
        if (! AppSetting::getForTenant($event->tenantId, 'auto_tagging_enabled', false)) {
            return;
        }

        // Guard: at least one ai_detectable tag must exist (skip DB query for the job)
        $hasDetectable = Tag::query()
            ->where('tenant_id', $event->tenantId)
            ->where('ai_detectable', true)
            ->exists();

        if (! $hasDetectable) {
            return;
        }

        TagLeadFromConversationJob::dispatch($event->leadId, 'status_change');
    }
}
