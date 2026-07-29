<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * The stack runs two replicas, each with its own `schedule:work`. A schedule that is not
 * ->onOneServer() therefore runs twice per tick on production and only once locally, so
 * nothing about a green local run reveals the duplicate. This asserts the property at the
 * schedule level rather than per command, to catch the next entry someone appends.
 */
test('every scheduled command is restricted to one server', function () {
    $unguarded = collect(app(Schedule::class)->events())
        ->reject(fn (Event $event): bool => $event->onOneServer)
        ->map(fn (Event $event): string => $event->command ?? $event->description ?? 'closure')
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});

test('the every-minute campaign starter cannot overlap itself', function () {
    // Two concurrent runs each read the pre-dispatch state to compute the remaining daily
    // budget, so the campaign fans out past its cap.
    $starter = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'start-scheduled-campaigns'));

    expect($starter)->not->toBeNull()
        ->and($starter->withoutOverlapping)->toBeTrue()
        ->and($starter->expiresAt)->toBeLessThanOrEqual(10);
});
