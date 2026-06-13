<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\FollowUpWindowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class LeadFollowUpController extends Controller
{
    public function pause(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        if ($lead->followup_status !== 'active') {
            throw ValidationException::withMessages([
                'followup_status' => 'Follow-up só pode ser pausado quando está ativo.',
            ]);
        }

        $lead->pauseFollowUp();

        return back()->with('flash', 'Follow-up pausado.');
    }

    public function resume(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        if ($lead->followup_status !== 'paused') {
            throw ValidationException::withMessages([
                'followup_status' => 'Follow-up só pode ser retomado quando está pausado.',
            ]);
        }

        $lead->resumeFollowUp();

        $fresh = $lead->fresh();
        if ($fresh && $fresh->followup_status !== 'active') {
            throw ValidationException::withMessages([
                'followup_status' => 'Não foi possível retomar: janela WhatsApp para mensagem livre expirou ou lead em status terminal.',
            ]);
        }

        return back()->with('flash', 'Follow-up retomado.');
    }

    /**
     * Permanently disable follow-up for the lead. Irreversible from this endpoint —
     * use `reactivate` if the operator needs to re-arm follow-up later.
     */
    public function disable(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        if ($lead->followup_status === 'inactive') {
            throw ValidationException::withMessages([
                'followup_status' => 'Follow-up já está desativado.',
            ]);
        }

        $lead->disableFollowUp();

        return back()->with('flash', 'Follow-up desativado.');
    }

    /**
     * Re-arm follow-up for a lead whose status is `inactive`. Requires the lead to
     * still be inside a WhatsApp free-form window and not in a terminal status.
     */
    public function reactivate(Lead $lead, FollowUpWindowService $window): RedirectResponse
    {
        $this->authorize('update', $lead);

        if ($lead->followup_status === 'active') {
            return back()->with('flash', 'Follow-up já está ativo.');
        }

        if (! $window->canSendFreeFormMessage($lead)) {
            throw ValidationException::withMessages([
                'followup_status' => 'Não foi possível reativar: janela WhatsApp para mensagem livre expirou.',
            ]);
        }

        if (in_array((string) $lead->status, ['optou_sair', 'convertido', 'escalado', 'desqualificado'], true)) {
            throw ValidationException::withMessages([
                'followup_status' => 'Lead em status terminal — follow-up não pode ser reativado.',
            ]);
        }

        $lead->activateFollowUp();

        return back()->with('flash', 'Follow-up reativado.');
    }
}
