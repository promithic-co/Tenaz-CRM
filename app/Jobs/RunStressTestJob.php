<?php

namespace App\Jobs;

use App\Models\StressTestCycle;
use App\Models\StressTestRun;
use App\Services\StressTestOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunStressTestJob implements ShouldQueue
{
    public $failOnTimeout = false;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public StressTestRun $run
    ) {}

    public function handle(StressTestOrchestrator $orchestrator): void
    {
        $run = $this->run->fresh();
        $run->update(['status' => 'running', 'started_at' => now()]);

        $dataset = $run->cpfDataset;
        $entries = $dataset
            ? $dataset->entries()->inRandomOrder()->limit($run->total_cycles)->get()
            : collect();

        for ($i = 1; $i <= $run->total_cycles; $i++) {
            if ($run->fresh()->status === 'cancelled') {
                break;
            }

            $entry = $entries->get($i - 1);
            $cycle = StressTestCycle::create([
                'stress_test_run_id' => $run->id,
                'cycle_number' => $i,
                'cpf_used' => $entry?->cpf,
                'status' => 'running',
            ]);

            try {
                $orchestrator->executeCycle($run, $cycle, $entry);
                $orchestrator->evaluateCycle($run, $cycle);
            } catch (Throwable $e) {
                $cycle->update([
                    'status' => 'failed',
                    'evaluation_report' => 'Erro: '.$e->getMessage(),
                ]);
                Log::error('stress_test.cycle_failed', [
                    'run_id' => $run->id,
                    'cycle' => $i,
                    'error' => $e->getMessage(),
                ]);
            }

            $run->increment('completed_cycles');
        }

        $orchestrator->finalizeRun($run->fresh());
    }

    public function failed(?Throwable $e): void
    {
        Log::error('stress_test.job_failed', [
            'run_id' => $this->run->id,
            'error' => $e?->getMessage(),
            'trace' => $e?->getTraceAsString(),
        ]);
        $this->run->update(['status' => 'failed']);
    }
}
