<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'disk' => $this->checkDisk(),
        ];

        // Only hard errors fail the probe. Soft "warning" signals (e.g. a disk
        // filling up) must not pull the instance out of the load balancer — they
        // are for alerting, not liveness.
        $hasError = collect($checks)->contains(fn ($c) => $c['status'] === 'error');

        return response()->json([
            'status' => $hasError ? 'unhealthy' : 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('credflow.build.sha', 'unknown'),
            'checks' => $checks,
        ], $hasError ? 503 : 200);
    }

    private function checkDatabase(): array
    {
        try {
            DB::selectOne('SELECT 1');

            return ['status' => 'ok', 'latency_ms' => $this->measure(fn () => DB::selectOne('SELECT 1'))];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_probe_'.uniqid();
            Cache::put($key, 'ok', 5);
            $val = Cache::get($key);
            Cache::forget($key);

            return $val === 'ok'
                ? ['status' => 'ok']
                : ['status' => 'error', 'message' => 'Cache write/read mismatch'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size('default');

            return ['status' => 'ok', 'queue_depth' => $size];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkDisk(): array
    {
        $freeBytes = disk_free_space(storage_path());
        $totalBytes = disk_total_space(storage_path());

        if ($freeBytes === false || $totalBytes === false) {
            return ['status' => 'error', 'message' => 'Unable to read disk stats'];
        }

        $usedPct = $totalBytes > 0 ? round((1 - $freeBytes / $totalBytes) * 100, 1) : 0.0;
        $status = $usedPct >= 90 ? 'warning' : 'ok';

        return [
            'status' => $status,
            'free_gb' => round($freeBytes / 1_073_741_824, 2),
            'used_pct' => $usedPct,
        ];
    }

    private function measure(callable $fn): int
    {
        $start = hrtime(true);
        $fn();

        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}
