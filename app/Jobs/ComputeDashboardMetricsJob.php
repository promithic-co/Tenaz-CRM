<?php

namespace App\Jobs;

use App\Events\DashboardMetricsUpdated;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ComputeDashboardMetricsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Stale metrics are worthless: any later metric change re-triggers a fresh
     * compute within seconds, so a failed run is dropped rather than retried.
     */
    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly string $tenantId)
    {
        $this->onQueue('default');
    }

    /**
     * Compute the tenant KPI snapshot off the hot send/inbound path and
     * broadcast it. SCALE-3: the triggering worker only enqueues this job.
     */
    public function handle(DashboardMetricsService $metrics): void
    {
        DashboardMetricsUpdated::dispatch($this->tenantId, $metrics->snapshot($this->tenantId));
    }
}
