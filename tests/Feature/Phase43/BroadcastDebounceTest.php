<?php

use App\Services\BroadcastDebouncer;
use Illuminate\Support\Facades\Cache;

it('test_debouncer_first_call_returns_true', function () {
    Cache::flush();
    $debouncer = app(BroadcastDebouncer::class);

    expect($debouncer->shouldFire('unit-key-first', 5))->toBeTrue();
});

it('test_debouncer_second_call_within_window_returns_false', function () {
    Cache::flush();
    $debouncer = app(BroadcastDebouncer::class);

    $debouncer->shouldFire('unit-key-second', 5);

    expect($debouncer->shouldFire('unit-key-second', 5))->toBeFalse();
});

it('test_debouncer_call_after_ttl_returns_true', function () {
    Cache::flush();
    $debouncer = app(BroadcastDebouncer::class);

    $debouncer->shouldFire('unit-key-ttl', 1);
    Cache::forget('broadcast:debounce:unit-key-ttl');

    expect($debouncer->shouldFire('unit-key-ttl', 1))->toBeTrue();
});

it('test_campaign_progress_debounced_per_campaign', function () {
    Cache::flush();
    $debouncer = app(BroadcastDebouncer::class);

    expect($debouncer->shouldFire('campaign:1:progress', 2))->toBeTrue()
        ->and($debouncer->shouldFire('campaign:2:progress', 2))->toBeTrue()
        ->and($debouncer->shouldFire('campaign:1:progress', 2))->toBeFalse()
        ->and($debouncer->shouldFire('campaign:2:progress', 2))->toBeFalse();
});

it('test_dashboard_metrics_debounced_per_tenant', function () {
    Cache::flush();
    $debouncer = app(BroadcastDebouncer::class);

    expect($debouncer->shouldFire('dashboard:tenant-1:metrics', 5))->toBeTrue()
        ->and($debouncer->shouldFire('dashboard:tenant-2:metrics', 5))->toBeTrue()
        ->and($debouncer->shouldFire('dashboard:tenant-1:metrics', 5))->toBeFalse();
});

it('CampaignProgressUpdated debounced 1 per 2s per campaign', function () {
    Cache::flush();
    $debouncer = app(BroadcastDebouncer::class);

    expect($debouncer->shouldFire('campaign:99:progress', 2))->toBeTrue();
    expect($debouncer->shouldFire('campaign:99:progress', 2))->toBeFalse();
});

it('DashboardMetricsUpdated debounced 1 per 5s per tenant', function () {
    Cache::flush();
    $debouncer = app(BroadcastDebouncer::class);

    expect($debouncer->shouldFire('dashboard:99:metrics', 5))->toBeTrue();
    expect($debouncer->shouldFire('dashboard:99:metrics', 5))->toBeFalse();
});
