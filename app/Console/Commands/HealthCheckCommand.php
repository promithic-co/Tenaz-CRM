<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthCheckCommand extends Command
{
    protected $signature = 'credflow:health {--json : Output JSON instead of table} {--alert : Exit 1 if any check is not ok}';

    protected $description = 'Run application health checks and report status.';

    public function handle(): int
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'disk' => $this->checkDisk(),
        ];

        if ($this->option('json')) {
            $allOk = collect($checks)->every(fn ($c) => $c['status'] === 'ok');
            $this->line(json_encode([
                'status' => $allOk ? 'healthy' : 'degraded',
                'checks' => $checks,
                'timestamp' => now()->toISOString(),
            ], JSON_PRETTY_PRINT));

            return $allOk ? Command::SUCCESS : ($this->option('alert') ? Command::FAILURE : Command::SUCCESS);
        }

        $rows = collect($checks)->map(fn ($check, $name) => [
            $name,
            $this->formatStatus($check['status']),
            collect($check)->except('status')->map(fn ($v, $k) => "{$k}={$v}")->implode(' '),
        ])->values()->all();

        $this->table(['Check', 'Status', 'Details'], $rows);

        $allOk = collect($checks)->every(fn ($c) => $c['status'] === 'ok');

        if (! $allOk) {
            $this->warn('Some checks are not healthy.');

            return $this->option('alert') ? Command::FAILURE : Command::SUCCESS;
        }

        $this->info('All checks passed.');

        return Command::SUCCESS;
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'ok' => '✓ ok',
            'warning' => '⚠ warning',
            default => '✗ error',
        };
    }

    private function checkDatabase(): array
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

    private function checkCache(): array
    {
        try {
            $key = 'health_check_'.uniqid();
            Cache::put($key, 'ok', 10);
            $val = Cache::get($key);
            Cache::forget($key);

            return $val === 'ok'
                ? ['status' => 'ok']
                : ['status' => 'error', 'message' => 'write/read mismatch'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size('default');

            return ['status' => 'ok', 'default_depth' => $size];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkDisk(): array
    {
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());

        if ($free === false || $total === false) {
            return ['status' => 'error', 'message' => 'unable to read disk'];
        }

        $usedPct = $total > 0 ? round((1 - $free / $total) * 100, 1) : 0.0;

        return [
            'status' => $usedPct >= 90 ? 'warning' : 'ok',
            'free_gb' => round($free / 1_073_741_824, 2),
            'used_pct' => $usedPct,
        ];
    }
}
