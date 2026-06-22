<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class SystemHealthService
{
    /**
     * @return array{status: string, latency_ms?: int, message?: string}
     */
    public function database(): array
    {
        try {
            $start = hrtime(true);
            DB::selectOne('SELECT 1');
            $ms = (int) round((hrtime(true) - $start) / 1_000_000);

            return ['status' => 'ok', 'latency_ms' => $ms];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, message?: string}
     */
    public function cache(): array
    {
        try {
            $key = 'health_lab_'.uniqid();
            Cache::put($key, 'ok', 5);
            $val = Cache::get($key);
            Cache::forget($key);

            return $val === 'ok' ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'mismatch'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, queue_depth?: int, queues?: array<string, int>, message?: string}
     */
    public function queue(): array
    {
        try {
            $queues = $this->queueDepths();

            return [
                'status' => 'ok',
                'queue_depth' => array_sum($queues),
                'queues' => $queues,
            ];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, int>
     */
    private function queueDepths(): array
    {
        return collect(config('queue.health_queues', ['default']))
            ->mapWithKeys(fn (string $queue): array => [$queue => Queue::size($queue)])
            ->all();
    }

    /**
     * @return array{status: string, free_gb?: float, used_pct?: float, message?: string}
     */
    public function disk(): array
    {
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());

        if ($free === false || $total === false) {
            return ['status' => 'error', 'message' => 'unavailable'];
        }

        $usedPct = $total > 0 ? round((1 - $free / $total) * 100, 1) : 0.0;

        return [
            'status' => $usedPct >= 90 ? 'warning' : 'ok',
            'free_gb' => round($free / 1_073_741_824, 2),
            'used_pct' => $usedPct,
        ];
    }

    /**
     * @return array{status: string, horizon_status?: string, message?: string}
     */
    public function horizon(): array
    {
        try {
            $status = Cache::get('horizon:status', 'unknown');

            return ['status' => $status === 'running' ? 'ok' : 'warning', 'horizon_status' => $status];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
