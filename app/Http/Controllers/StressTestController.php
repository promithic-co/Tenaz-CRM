<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCpfDatasetRequest;
use App\Http\Requests\StoreStressTestRunRequest;
use App\Jobs\RunStressTestJob;
use App\Models\CpfDataset;
use App\Models\CpfDatasetEntry;
use App\Models\StressTestRun;
use App\Services\CpfDatasetImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StressTestController extends Controller
{
    public function __construct(
        private readonly CpfDatasetImporter $importer
    ) {}

    public function datasets(): JsonResponse
    {
        $datasets = CpfDataset::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'description', 'total_entries', 'created_at']);

        return response()->json(['data' => $datasets]);
    }

    public function storeDataset(StoreCpfDatasetRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->getRealPath();
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        if ($ext === 'json' || $file->getMimeType() === 'application/json') {
            $dataset = $this->importer->importFromJson(
                $path,
                $request->string('name')->toString(),
                $request->input('description'),
                Auth::id()
            );
        } else {
            $dataset = $this->importer->importFromCsv(
                $path,
                $request->string('name')->toString(),
                $request->input('description'),
                Auth::id()
            );
        }

        $this->ensureDatasetOwnership($dataset);

        return response()->json([
            'data' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'description' => $dataset->description,
                'total_entries' => $dataset->total_entries,
                'created_at' => $dataset->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function showDataset(CpfDataset $dataset): JsonResponse
    {
        $this->ensureDatasetOwnership($dataset);

        $entries = CpfDatasetEntry::where('cpf_dataset_id', $dataset->id)
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'cpf', 'nome', 'status_expected', 'qualified_json']);

        $preloadedCount = $dataset->entries()->whereNotNull('qualified_json')->count();

        return response()->json([
            'data' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'description' => $dataset->description,
                'total_entries' => $dataset->total_entries,
                'preloaded_count' => $preloadedCount,
                'entries_preview' => $entries->map(fn ($e) => [
                    'id' => $e->id,
                    'cpf' => $e->cpf,
                    'nome' => $e->nome,
                    'status_expected' => $e->status_expected,
                    'has_qualified_json' => ! empty($e->qualified_json),
                ]),
            ],
        ]);
    }

    public function destroyDataset(CpfDataset $dataset): JsonResponse
    {
        $this->ensureDatasetOwnership($dataset);
        $dataset->delete();

        return response()->json(['ok' => true]);
    }

    public function prefetchDataset(CpfDataset $dataset): JsonResponse
    {
        $this->ensureDatasetOwnership($dataset);
        $count = $this->importer->prefetchPromosysData($dataset);

        return response()->json([
            'data' => [
                'updated_count' => $count,
                'total_entries' => $dataset->fresh()->total_entries,
            ],
        ]);
    }

    public function runs(): JsonResponse
    {
        $runs = StressTestRun::where('user_id', Auth::id())
            ->with('cpfDataset:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $runs->map(fn ($run) => [
                'id' => $run->id,
                'label' => $run->label,
                'objective' => $run->objective,
                'cpf_dataset_id' => $run->cpf_dataset_id,
                'cpf_dataset' => $run->cpfDataset ? ['id' => $run->cpfDataset->id, 'name' => $run->cpfDataset->name] : null,
                'config' => $run->config,
                'status' => $run->status,
                'total_cycles' => $run->total_cycles,
                'completed_cycles' => $run->completed_cycles,
                'results_summary' => $run->results_summary,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'created_at' => $run->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function storeRun(StoreStressTestRunRequest $request): JsonResponse
    {
        $config = $request->input('config');
        $totalCycles = (int) ($config['cycles'] ?? 5);

        if ($request->filled('cpf_dataset_id')) {
            CpfDataset::where('id', $request->input('cpf_dataset_id'))
                ->where('user_id', Auth::id())
                ->firstOrFail();
        }

        $run = StressTestRun::create([
            'user_id' => Auth::id(),
            'cpf_dataset_id' => $request->input('cpf_dataset_id'),
            'label' => $request->string('label')->toString(),
            'objective' => $request->string('objective')->toString(),
            'config' => $config,
            'status' => 'pending',
            'total_cycles' => $totalCycles,
            'completed_cycles' => 0,
        ]);

        RunStressTestJob::dispatch($run);

        return response()->json([
            'data' => [
                'id' => $run->id,
                'label' => $run->label,
                'status' => $run->status,
                'total_cycles' => $run->total_cycles,
                'created_at' => $run->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function showRun(StressTestRun $run): JsonResponse
    {
        $this->ensureRunOwnership($run);

        $run->load(['cycles', 'cpfDataset:id,name']);
        $cycles = $run->cycles->map(fn ($c) => [
            'id' => $c->id,
            'cycle_number' => $c->cycle_number,
            'cpf_used' => $c->cpf_used,
            'scenario' => $c->scenario,
            'status' => $c->status,
            'fidelity_score' => $c->fidelity_score !== null ? (float) $c->fidelity_score : null,
            'hallucinations' => $c->hallucinations,
            'token_metrics' => $c->token_metrics,
            'evaluation_report' => $c->evaluation_report,
            'completed_at' => $c->completed_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'id' => $run->id,
                'label' => $run->label,
                'objective' => $run->objective,
                'cpf_dataset_id' => $run->cpf_dataset_id,
                'cpf_dataset' => $run->cpfDataset ? ['id' => $run->cpfDataset->id, 'name' => $run->cpfDataset->name] : null,
                'config' => $run->config,
                'status' => $run->status,
                'total_cycles' => $run->total_cycles,
                'completed_cycles' => $run->completed_cycles,
                'results_summary' => $run->results_summary,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'created_at' => $run->created_at->toIso8601String(),
                'cycles' => $cycles,
            ],
        ]);
    }

    public function cancelRun(StressTestRun $run): JsonResponse
    {
        $this->ensureRunOwnership($run);

        if (! in_array($run->status, ['pending', 'running'], true)) {
            return response()->json(['message' => 'Run cannot be cancelled.'], 422);
        }

        $run->update(['status' => 'cancelled']);

        return response()->json([
            'data' => [
                'id' => $run->id,
                'status' => $run->status,
            ],
        ]);
    }

    private function ensureDatasetOwnership(CpfDataset $dataset): void
    {
        if ($dataset->user_id !== Auth::id()) {
            abort(403, 'Dataset does not belong to you.');
        }
    }

    private function ensureRunOwnership(StressTestRun $run): void
    {
        if ($run->user_id !== Auth::id()) {
            abort(403, 'Stress test run does not belong to you.');
        }
    }
}
