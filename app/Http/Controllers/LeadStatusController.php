<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\StatusMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Handles manual status transitions for individual leads from the CRM UI.
 *
 * The requested status only has to exist in the tenant's StatusMachine — the
 * transition graph is not enforced here. It governs automation (AI tools,
 * lifecycle services) via Lead::canTransitionTo; an operator is free to move a
 * lead anywhere, including back out of a terminal status, which is the same
 * freedom the Kanban already grants in PipelineController::move.
 *
 * The Lead model's `updated` observer emits LeadStatusChanged when the `status`
 * column changes, which Phase 49 broadcasts to the Kanban.
 */
class LeadStatusController extends Controller
{
    /**
     * Transition a lead to a new status.
     *
     * An unknown slug raises a 422 ValidationException. Returning to the same
     * status is a no-op (idempotent for retried requests).
     */
    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $knownSlugs = StatusMachine::forTenant((string) $lead->tenant_id)
            ->getStatuses()
            ->pluck('slug')
            ->all();

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in($knownSlugs)],
        ], [
            'status.in' => 'Status desconhecido: não existe no pipeline deste tenant.',
        ]);

        $newStatus = $data['status'];

        if ($newStatus !== $lead->status) {
            $lead->update([
                'status' => $newStatus,
                'ai_paused_until' => now()->addHours(24),
                'ai_paused_by' => $request->user()->id,
                'ai_paused_reason' => 'manual_status_override',
            ]);
        }

        return back(303);
    }
}
