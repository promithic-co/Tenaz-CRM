<?php

namespace App\Console\Commands;

use App\Models\FailedInteraction;
use App\Services\AlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LaboratoryHealthCheckCommand extends Command
{
    protected $signature = 'laboratory:health-check';

    protected $description = 'Run automated health checks and alert on threshold breaches';

    public function handle(AlertService $alertService): int
    {
        $thresholds = config('laboratory.alerting.thresholds');
        $alerts = [];

        // 1. Check queue backlog
        $queueSize = DB::table('jobs')->count();
        if ($queueSize > $thresholds['queue_backlog']) {
            $alerts[] = [
                'type' => 'queue_backlog',
                'message' => "Fila com {$queueSize} jobs pendentes (limite: {$thresholds['queue_backlog']})",
            ];
        }

        // 2. Check failed retries in last hour
        $failedRetries = FailedInteraction::where('status', 'escalated')
            ->where('updated_at', '>=', now()->subHour())
            ->count();
        if ($failedRetries > $thresholds['failed_retries_hourly']) {
            $alerts[] = [
                'type' => 'retry_failures',
                'message' => "{$failedRetries} interações escaladas na última hora (limite: {$thresholds['failed_retries_hourly']})",
            ];
        }

        // 3. Check error rate (failed interactions / total in last hour)
        $totalInteractionsLastHour = DB::table('agent_conversation_messages')
            ->where('created_at', '>=', now()->subHour())
            ->where('role', 'user')
            ->count();
        $failuresLastHour = FailedInteraction::where('created_at', '>=', now()->subHour())->count();

        if ($totalInteractionsLastHour > 0) {
            $errorRate = ($failuresLastHour / $totalInteractionsLastHour) * 100;
            if ($errorRate > $thresholds['error_rate_percent']) {
                $alerts[] = [
                    'type' => 'high_error_rate',
                    'message' => "Taxa de erro: {$errorRate}% na última hora ({$failuresLastHour}/{$totalInteractionsLastHour})",
                ];
            }
        }

        // 4. Check pending retries that are overdue
        $overdueRetries = FailedInteraction::where('status', 'pending')
            ->where('next_retry_at', '<', now()->subMinutes(30))
            ->count();
        if ($overdueRetries > 0) {
            $alerts[] = [
                'type' => 'overdue_retries',
                'message' => "{$overdueRetries} retries pendentes há mais de 30 minutos — verificar se o scheduler está rodando",
            ];
        }

        foreach ($alerts as $alert) {
            $alertService->sendAlert($alert['type'], $alert['message']);
            $this->warn("[ALERT] {$alert['type']}: {$alert['message']}");
        }

        if (empty($alerts)) {
            $this->info('All checks passed.');
        }

        return self::SUCCESS;
    }
}
