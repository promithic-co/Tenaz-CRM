<?php

namespace App\Http\Controllers;

use App\Http\Requests\ColumnPipelineRequest;
use App\Http\Requests\IndexPipelineRequest;
use App\Http\Requests\MoveLeadRequest;
use App\Models\Lead;
use App\Models\StatusMachine;
use App\Services\PipelineBoardPropsBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function __construct(
        private readonly PipelineBoardPropsBuilder $boardProps,
    ) {}

    public function index(IndexPipelineRequest $request): Response
    {
        $tenantId = (string) $request->user()->tenantId;
        $filters = $request->validated();

        return Inertia::render('pipeline/Index', $this->boardProps->buildIndex($filters, $tenantId));
    }

    public function column(ColumnPipelineRequest $request, string $slug): JsonResponse
    {
        $tenantId = (string) $request->user()->tenantId;

        return response()->json($this->boardProps->buildColumn($request->validated(), $tenantId, $slug));
    }

    public function move(MoveLeadRequest $request): RedirectResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = StatusMachine::forTenant($tenantId);
        $fromStatus = (string) $request->string('from_status');
        $toStatus = (string) $request->string('to_status');
        $visibleStatusSlugs = collect($this->boardProps->boardStatuses($machine))->pluck('slug')->all();

        $lead = Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $request->integer('lead_id'))
            ->firstOrFail();

        if (! in_array($toStatus, $visibleStatusSlugs, true)) {
            return back()->withErrors([
                'to_status' => 'Status de destino nao esta disponivel no Kanban.',
            ]);
        }

        if (! in_array((string) $lead->status, $visibleStatusSlugs, true)) {
            return back()->withErrors([
                'from_status' => 'Status atual do lead nao esta disponivel no Kanban.',
            ]);
        }

        if ((string) $lead->status !== $fromStatus) {
            return back()->withErrors([
                'from_status' => 'Status do lead mudou. Recarregue o Kanban e tente novamente.',
            ]);
        }

        $lead->update([
            'status' => $toStatus,
            'ai_paused_until' => now()->addHours(24),
            'ai_paused_by' => $request->user()->id,
            'ai_paused_reason' => 'manual_status_override',
        ]);

        return back();
    }
}
