<?php

use App\Console\Commands\AutoCloseIdleConversationSessionsCommand;
use App\Console\Commands\CleanOldMediaFilesCommand;
use App\Console\Commands\LaboratoryHealthCheckCommand;
use App\Console\Commands\ProcessPendingRetriesCommand;
use App\Console\Commands\SyncMetaHealthCommand;
use App\Console\Commands\SyncTemplatesCommand;
use App\Models\AgentInteractionEvent;
use App\Models\AiRun;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Every schedule below is ->onOneServer(). The stack runs two replicas of the app image
 * and each container starts its own `schedule:work`, so without the lock every command
 * here fires twice a tick. The duplicate run is not harmless: two concurrent
 * `credflow:start-scheduled-campaigns` each compute the remaining daily budget from the
 * same pre-dispatch state, so a campaign can fan out past its cap. The lock is taken in
 * the shared Redis cache store (CACHE_STORE=redis), which is what makes it work across
 * containers — a per-container store would hand each replica its own lock and change
 * nothing.
 *
 * The frequent commands additionally carry ->withoutOverlapping() with an explicit
 * expiry. Laravel releases that mutex on normal completion, but the container has been
 * restarted mid-run before; the expiry bounds how long a stale lock can wedge the
 * schedule.
 */
Schedule::command(CleanOldMediaFilesCommand::class)->dailyAt('03:00')->onOneServer();
Schedule::command('credflow:purge-old-credits')->dailyAt('04:00')->onOneServer();
Schedule::command('credflow:check-followups')->everyFiveMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command(ProcessPendingRetriesCommand::class)->everyFiveMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command(LaboratoryHealthCheckCommand::class)->everyFifteenMinutes()->onOneServer();
Schedule::command('credflow:aggregate-usage')->dailyAt('01:00')->onOneServer();
Schedule::command('credflow:start-scheduled-campaigns')->everyMinute()->onOneServer()->withoutOverlapping(5);
Schedule::command('credflow:monitor-campaigns')->everyFiveMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('credflow:reconcile-outbox')->hourly()->onOneServer()->withoutOverlapping(60);
Schedule::command(SyncTemplatesCommand::class)->daily()->onOneServer();
Schedule::command(SyncMetaHealthCommand::class)->everyFifteenMinutes()->onOneServer()->withoutOverlapping(30);
Schedule::command(AutoCloseIdleConversationSessionsCommand::class)->dailyAt('03:30')->onOneServer();

// GROW-4: prune append-only observability tables (AgentInteractionEvent, AiRun)
// past their configured retention window so they don't grow unbounded.
Schedule::command('model:prune', [
    '--model' => [
        AgentInteractionEvent::class,
        AiRun::class,
    ],
])->dailyAt('02:30')->onOneServer();
