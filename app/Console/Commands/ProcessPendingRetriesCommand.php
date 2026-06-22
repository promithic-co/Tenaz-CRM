<?php

namespace App\Console\Commands;

use App\Jobs\RetryFailedInteractionJob;
use App\Models\FailedInteraction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPendingRetriesCommand extends Command
{
    protected $signature = 'laboratory:process-retries';

    protected $description = 'Dispatch retry jobs for pending failed interactions within business hours';

    public function handle(): int
    {
        try {
            return $this->processRetries();
        } catch (\Throwable $e) {
            Log::error('laboratory:process-retries failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function processRetries(): int
    {
        $pending = FailedInteraction::pending()
            ->inBusinessHours()
            ->with(['lead', 'agent'])
            ->limit(50)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending retries to process.');

            return self::SUCCESS;
        }

        $jitter = (int) config('credflow.jobs.cron_dispatch_jitter_seconds', 0);

        foreach ($pending as $failure) {
            RetryFailedInteractionJob::dispatch($failure)
                ->delay($jitter > 0 ? now()->addSeconds(random_int(0, $jitter)) : null);
        }

        $this->info("Dispatched {$pending->count()} retry jobs.");

        return self::SUCCESS;
    }
}
