<?php

namespace App\Http\Controllers;

use App\Exceptions\StatusMachine\CanonicalStatusModificationException;
use App\Exceptions\StatusMachine\DuplicateSlugException;
use App\Exceptions\StatusMachine\ProtectedTransitionException;
use App\Exceptions\StatusMachine\StatusInUseException;
use App\Http\Requests\ReorderStatusesRequest;
use App\Http\Requests\StoreStatusRequest;
use App\Http\Requests\StoreTransitionRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Models\Lead;
use App\Models\StatusMachine;
use App\Services\StatusMachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exposes the tenant's StatusMachine pipeline for admin management.
 *
 * All mutating endpoints are guarded by the `role:owner,administrator`
 * middleware applied in routes/web.php. The controller delegates all
 * business logic to StatusMachineService.
 */
class StatusPipelineController extends Controller
{
    public function __construct(
        private readonly StatusMachineService $service,
    ) {}

    /**
     * Render the pipeline admin page.
     *
     * Passes statuses, transitions, per-status lead counts, and the list
     * of canonical slugs so the Vue page can protect canonical items.
     */
    public function index(Request $request): Response
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = StatusMachine::forTenant($tenantId);

        $leadCountsByStatus = Lead::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('status')
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->pluck('count', 'status')
            ->all();

        return Inertia::render('configuracoes/pipeline/Index', [
            'statuses' => $machine->getStatuses()->sortBy('position')->values()->all(),
            'transitions' => $machine->transitions ?? [],
            'lead_counts_by_status' => $leadCountsByStatus,
            'canonical_slugs' => StatusMachine::CANONICAL_SLUGS,
            'initial_status' => $machine->initial_status,
            'has_persisted_machine' => $machine->exists,
        ]);
    }

    /**
     * Update label, color, position, or is_terminal of a status.
     * Slug and is_canonical are always immutable.
     */
    public function updateStatus(UpdateStatusRequest $request, string $slug): JsonResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = $this->service->getOrCreateForTenant($tenantId);

        try {
            $this->service->updateStatus($machine, $slug, $request->validated());
        } catch (CanonicalStatusModificationException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        $machine->refresh();

        return response()->json([
            'statuses' => $machine->getStatuses()->sortBy('position')->values()->all(),
        ]);
    }

    /**
     * Add a new custom status to the pipeline.
     */
    public function storeStatus(StoreStatusRequest $request): JsonResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = $this->service->getOrCreateForTenant($tenantId);

        try {
            $newStatus = $this->service->addCustomStatus($machine, $request->validated());
        } catch (DuplicateSlugException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return response()->json(['status' => $newStatus], 201);
    }

    /**
     * Delete a custom status. Returns 409 when leads are still assigned.
     */
    public function destroyStatus(Request $request, string $slug): JsonResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = $this->service->getOrCreateForTenant($tenantId);

        try {
            $this->service->deleteCustomStatus($machine, $slug);
        } catch (CanonicalStatusModificationException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        } catch (StatusInUseException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        $machine->refresh();
        $leadCountsByStatus = Lead::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('status')
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->pluck('count', 'status')
            ->all();

        return response()->json([
            'statuses' => $machine->getStatuses()->sortBy('position')->values()->all(),
            'lead_counts_by_status' => $leadCountsByStatus,
        ]);
    }

    /**
     * Add a transition between two existing statuses.
     */
    public function storeTransition(StoreTransitionRequest $request): JsonResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = $this->service->getOrCreateForTenant($tenantId);

        try {
            $this->service->addTransition($machine, $request->input('from'), $request->input('to'));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['from' => $e->getMessage()]);
        }

        $machine->refresh();

        return response()->json(['transitions' => $machine->transitions]);
    }

    /**
     * Remove a transition. Returns 422 when the transition is canonical.
     */
    public function destroyTransition(Request $request, string $from, string $to): JsonResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = $this->service->getOrCreateForTenant($tenantId);

        try {
            $this->service->removeTransition($machine, $from, $to);
        } catch (ProtectedTransitionException $e) {
            throw ValidationException::withMessages(['transition' => $e->getMessage()]);
        }

        $machine->refresh();

        return response()->json(['transitions' => $machine->transitions]);
    }

    /**
     * Reorder statuses by providing a full slug array in the desired order.
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        $tenantId = (string) $request->user()->tenantId;
        $machine = $this->service->getOrCreateForTenant($tenantId);

        try {
            $this->service->reorder($machine, $request->input('slugs', []));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['slugs' => $e->getMessage()]);
        }

        $machine->refresh();

        return response()->json([
            'statuses' => $machine->getStatuses()->sortBy('position')->values()->all(),
        ]);
    }

    /**
     * Reset the pipeline to the default INSS machine.
     *
     * Requires the header `X-Confirm: 1` to prevent accidental resets.
     * Returns 400 if the confirmation header is missing.
     */
    public function reset(Request $request): JsonResponse
    {
        if ($request->header('X-Confirm') !== '1') {
            return response()->json([
                'message' => 'Operação destrutiva. Envie o header X-Confirm: 1 para confirmar.',
            ], 400);
        }

        $tenantId = (string) $request->user()->tenantId;
        $machine = $this->service->getOrCreateForTenant($tenantId);

        try {
            $this->service->resetToDefault($machine);
        } catch (StatusInUseException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $machine->refresh();

        return response()->json([
            'statuses' => $machine->getStatuses()->sortBy('position')->values()->all(),
            'transitions' => $machine->transitions,
        ]);
    }
}
