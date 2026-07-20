<?php

use App\Console\Commands\AutoCloseIdleConversationSessionsCommand;
use App\Console\Commands\CleanOldMediaFilesCommand;
use App\Console\Commands\LaboratoryHealthCheckCommand;
use App\Console\Commands\ProcessPendingRetriesCommand;
use App\Console\Commands\SyncTemplatesCommand;
use App\Models\AgentInteractionEvent;
use App\Models\AiRun;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CleanOldMediaFilesCommand::class)->dailyAt('03:00');
Schedule::command('credflow:purge-old-credits')->dailyAt('04:00');
Schedule::command('credflow:check-followups')->everyFiveMinutes();
Schedule::command(ProcessPendingRetriesCommand::class)->everyFiveMinutes();
Schedule::command(LaboratoryHealthCheckCommand::class)->everyFifteenMinutes();
Schedule::command('credflow:aggregate-usage')->dailyAt('01:00');
Schedule::command('credflow:start-scheduled-campaigns')->everyMinute();
Schedule::command('credflow:monitor-campaigns')->everyFiveMinutes();
Schedule::command('credflow:reconcile-outbox')->hourly();
Schedule::command(SyncTemplatesCommand::class)->daily();
Schedule::command(AutoCloseIdleConversationSessionsCommand::class)->dailyAt('03:30');

// GROW-4: prune append-only observability tables (AgentInteractionEvent, AiRun)
// past their configured retention window so they don't grow unbounded.
Schedule::command('model:prune', [
    '--model' => [
        AgentInteractionEvent::class,
        AiRun::class,
    ],
])->dailyAt('02:30');
