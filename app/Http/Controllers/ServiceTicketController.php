<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveTicketRequest;
use App\Models\ServiceTicket;
use App\Services\ServiceTicketBoardService;
use App\Services\ServiceTicketLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceTicketController extends Controller
{
    public function __construct(private readonly ServiceTicketLifecycleService $lifecycle) {}

    public function index(Request $request, ServiceTicketBoardService $board): Response
    {
        $motivo = $request->query('motivo', '');
        $data_inicio = $request->query('data_inicio', '');
        $data_fim = $request->query('data_fim', '');

        $result = $board->build(auth()->user(), [
            'motivo' => $motivo,
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim,
        ]);

        return Inertia::render('atendimentos/Index', [
            'tenantId' => auth()->user()->tenantId,
            'buckets' => $result['buckets'],
            'counters' => $result['counters'],
            'filters' => [
                'motivo' => $motivo,
                'data_inicio' => $data_inicio,
                'data_fim' => $data_fim,
            ],
        ]);
    }

    public function claim(ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        try {
            $this->lifecycle->claim($ticket, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->with('flash_error', $e->getMessage());
        }

        return back()->with('flash', 'Atendimento assumido.');
    }

    public function disableFollowUp(ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $ticket->loadMissing('lead');

        if (! $ticket->lead) {
            return back()->with('flash', 'Lead nao encontrado para este atendimento.');
        }

        if (($ticket->lead->followup_status ?: 'inactive') === 'inactive') {
            return back()->with('flash', 'Follow-up ja estava desligado para este lead.');
        }

        $ticket->lead->disableFollowUp();

        return back()->with('flash', 'Follow-up desligado para este lead.');
    }

    public function resolve(ResolveTicketRequest $request, ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validated();

        $this->lifecycle->resolve(
            $ticket,
            auth()->user(),
            $data['resolution_reason'] ?? null,
            $data['resolution_notes'] ?? null,
        );

        return back()->with('flash', 'Atendimento resolvido.');
    }

    public function close(ResolveTicketRequest $request, ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validated();

        $this->lifecycle->close(
            $ticket,
            auth()->user(),
            $data['resolution_reason'] ?? null,
            $data['resolution_notes'] ?? null,
        );

        return back()->with('flash', 'Atendimento fechado.');
    }

    public function returnToAi(ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $this->lifecycle->returnToAi($ticket, auth()->user());

        return back()->with('flash', 'Atendimento devolvido para IA.');
    }

    public function keepManual(ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $this->lifecycle->keepManual($ticket, auth()->user());

        return back()->with('flash', 'Atendimento encerrado. Lead permanece em modo manual.');
    }
}
