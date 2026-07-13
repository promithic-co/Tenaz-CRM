<?php

namespace App\Console\Commands;

use App\Jobs\ProcessLeadFollowUpJob;
use App\Models\Lead;
use App\Services\AlertService;
use App\Services\ConversationAutomationService;
use App\Services\FollowUpSettingsResolver;
use App\Services\FollowUpWindowService;
use App\Services\PauseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckFollowUpsCommand extends Command
{
    protected $signature = 'credflow:check-followups';

    protected $description = 'Avalia e despacha mensagens de follow-up para leads elegiveis.';

    public function handle(): int
    {
        try {
            return $this->runCheck(now());
        } catch (\Throwable $e) {
            Log::error('credflow:check-followups failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function runCheck($now): int
    {
        $dispatchedCount = 0;
        $settingsResolver = app(FollowUpSettingsResolver::class);
        $window = app(FollowUpWindowService::class);
        $pause = app(PauseService::class);
        $automation = app(ConversationAutomationService::class);
        $jitter = (int) config('credflow.jobs.cron_dispatch_jitter_seconds', 0);

        $windowHours = FollowUpWindowService::CUSTOMER_SERVICE_WINDOW_HOURS;

        $deactivated = Lead::where('followup_status', 'active')
            ->where('is_sandbox', false)
            ->where(function ($query) use ($now, $windowHours): void {
                $query->where(function ($serviceQuery) use ($now): void {
                    $serviceQuery->whereNotNull('service_window_expires_at')
                        ->where('service_window_expires_at', '<', $now);
                })->orWhere(function ($legacyQuery) use ($now, $windowHours): void {
                    $legacyQuery->whereNull('service_window_expires_at')
                        ->where(function ($inboundQuery) use ($now, $windowHours): void {
                            $inboundQuery->whereNull('last_inbound_at')
                                ->orWhere('last_inbound_at', '<', $now->copy()->subHours($windowHours));
                        });
                });
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('free_entry_point_expires_at')
                    ->orWhere('free_entry_point_expires_at', '<', $now);
            })
            ->update(['followup_status' => 'inactive']);

        if ($deactivated > 0) {
            Log::info('credflow:check-followups: deactivated leads outside customer window', [
                'count' => $deactivated,
                'reason' => 'window_expired_or_missing',
            ]);
        }

        $recentFailureCount = DB::table('failed_jobs')
            ->where('failed_at', '>=', $now->copy()->subHour())
            ->where('queue', 'followups')
            ->count();

        if ($recentFailureCount > 5) {
            app(AlertService::class)->sendAlert(
                'FollowUpJobFailing',
                "Alerta: {$recentFailureCount} follow-up jobs falharam na ultima hora",
                ['count' => $recentFailureCount, 'threshold' => 5]
            );
        }

        Lead::where('followup_status', 'active')
            ->where('is_sandbox', false)
            ->where(function ($query) use ($now): void {
                // Inbound leads (24h customer-service window) OR leads whose 72h free
                // entry point is still open (F7) — evaluate() supports both paths.
                $query->whereNotNull('last_inbound_at')
                    ->orWhere('free_entry_point_expires_at', '>', $now);
            })
            ->where(function ($query): void {
                $query->whereHas('agent', fn ($q) => $q->where('is_active', true))
                    ->orWhereNull('agent_id');
            })
            ->chunkById((int) config('credflow.followup.check_chunk_size', 200), function ($leads) use ($now, &$dispatchedCount, $settingsResolver, $window, $pause, $automation, $jitter): void {
                $effectiveModes = $automation->resolveInstanceDefaultedModes($leads);

                foreach ($leads as $lead) {
                    $settings = $settingsResolver->forLead($lead);
                    $evaluation = $window->evaluate($lead, $settings, $now, $pause, $effectiveModes[$lead->id] ?? null);

                    if ($evaluation['eligible']) {
                        // Dedup handled by ProcessLeadFollowUpJob::ShouldBeUnique (uniqueId = lead_id).
                        Log::info('credflow:check-followups: dispatching', [
                            'lead_id' => $lead->id,
                            'lead_nome' => $lead->nome,
                            'followup_count' => $lead->followup_count,
                            'whatsapp_instance_id' => $lead->whatsapp_instance_id,
                            'last_inbound_at' => $lead->last_inbound_at?->toIso8601String(),
                        ]);

                        ProcessLeadFollowUpJob::dispatch($lead)
                            ->delay($jitter > 0 ? now()->addSeconds(random_int(0, $jitter)) : null);
                        $dispatchedCount++;

                        continue;
                    }

                    if (in_array($evaluation['reason'], ['window_expired', 'window_expired_requires_hsm', 'no_inbound_window', 'terminal_status', 'max_reached'], true)) {
                        $lead->update(['followup_status' => 'inactive']);
                    } elseif ($evaluation['reason'] === 'human_paused') {
                        $lead->update(['followup_status' => 'paused']);
                    }

                    Log::debug('credflow:check-followups: skipped', [
                        'lead_id' => $lead->id,
                        'followup_count' => $lead->followup_count,
                        'reason' => $evaluation['reason'],
                        'due_at' => $evaluation['due_at'],
                        'window_expires_at' => $evaluation['window_expires_at'],
                        'remaining_minutes' => $evaluation['remaining_minutes'],
                    ]);
                }
            });

        $this->info("Follow-ups checados. {$dispatchedCount} leads despachados para fila.");
        Log::info("Command credflow:check-followups executado. {$dispatchedCount} leads disparados.");

        return Command::SUCCESS;
    }
}
