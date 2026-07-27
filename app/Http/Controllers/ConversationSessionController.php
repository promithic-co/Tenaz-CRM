<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseConversationSessionRequest;
use App\Http\Requests\StoreConversationSessionRequest;
use App\Http\Requests\UpdateConversationSessionValueRequest;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Services\ConversationSessionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Manual atendimento (ConversationSession) controls exposed to operators.
 *
 * Sessions are always addressed as a child of their lead (nested, scope-bound route)
 * so a session can never be resolved by a loose id — authorization runs against the
 * parent lead and the tenant global scope keeps cross-tenant sessions at a 404.
 */
class ConversationSessionController extends Controller
{
    public function __construct(private readonly ConversationSessionLifecycleService $sessions) {}

    /**
     * Open a manual atendimento for the lead. The one-open-per-lead invariant means
     * this reuses the current open session when one already exists.
     */
    public function store(StoreConversationSessionRequest $request, Lead $lead): RedirectResponse
    {
        $session = $this->sessions->ensureOpenSession($lead, ConversationSession::OPEN_REASON_MANUAL);

        return back()->with('flash', $session->wasRecentlyCreated
            ? 'Novo atendimento aberto.'
            : 'Já existe um atendimento aberto para este contato.');
    }

    /**
     * Close the given atendimento with the operator-selected outcome. Scope bindings
     * guarantee the session belongs to the lead; close() is idempotent.
     */
    public function close(
        CloseConversationSessionRequest $request,
        Lead $lead,
        ConversationSession $session,
    ): RedirectResponse {
        $this->sessions->close($session, $request->string('outcome')->toString(), $request->user());

        return back()->with('flash', 'Atendimento encerrado.');
    }

    /**
     * Price the atendimento: negotiated amount and forecast close date.
     *
     * Only while the cycle is open. A closed atendimento has already been counted into the
     * dashboard's won/lost totals, so editing its amount afterwards would silently restate a
     * historical figure with no trail of who changed it or when.
     */
    public function updateValue(
        UpdateConversationSessionValueRequest $request,
        Lead $lead,
        ConversationSession $session,
    ): RedirectResponse {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'value_cents' => 'Este atendimento já foi encerrado e não pode mais ser alterado.',
            ]);
        }

        $session->update([
            'value_cents' => $request->input('value_cents'),
            'expected_close_at' => $request->input('expected_close_at'),
        ]);

        return back()->with('flash', 'Valor do atendimento atualizado.');
    }
}
