<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseConversationSessionRequest;
use App\Http\Requests\StoreConversationSessionRequest;
use App\Http\Requests\UpdateConversationSessionInformationRequest;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Services\ConversationSessionInformationService;
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
     * Upsert or delete a free-form information entry on the open atendimento.
     *
     * Only while the cycle is open: a closed atendimento is an archived record of what
     * happened in that cycle, so its entries are frozen alongside its outcome.
     */
    public function updateInformation(
        UpdateConversationSessionInformationRequest $request,
        Lead $lead,
        ConversationSession $session,
        ConversationSessionInformationService $information,
    ): RedirectResponse {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'value' => 'Este atendimento já foi encerrado e não pode mais ser alterado.',
            ]);
        }

        /** @var array{operation: 'upsert'|'delete', key?: string|null, label?: string|null, value?: string|null} $data */
        $data = $request->validated();

        $information->applyManual($session, $data);

        return back()->with('flash', 'Informação do atendimento atualizada.');
    }
}
