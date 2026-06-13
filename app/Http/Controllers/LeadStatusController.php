<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Handles manual status transitions for individual leads from the CRM UI.
 *
 * Validates the requested transition against the tenant's StatusMachine before
 * applying. The Lead model's `updated` observer emits LeadStatusChanged when
 * the `status` column changes, which Phase 49 broadcasts to the Kanban.
 */
class LeadStatusController extends Controller
{
    /**
     * Transition a lead to a new status.
     *
     * The requested status must be reachable from the lead's current status
     * according to the tenant's StatusMachine. Invalid transitions raise a
     * 422 ValidationException with a human-readable message. Returning to
     * the same status is a no-op (idempotent for retried requests).
     *
     * @throws ValidationException when the transition is not allowed
     */
    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $data = $request->validate([
            'status' => ['required', 'string', 'min:1', 'max:80'],
        ]);

        $newStatus = $data['status'];

        if ($newStatus !== $lead->status) {
            if (! $lead->canTransitionTo($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => "Transição inválida: o lead não pode mover de '{$lead->status}' para '{$newStatus}'.",
                ]);
            }

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
