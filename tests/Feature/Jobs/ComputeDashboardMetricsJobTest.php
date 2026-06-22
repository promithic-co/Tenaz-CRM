<?php

use App\Events\DashboardMetricsUpdated;
use App\Jobs\ComputeDashboardMetricsJob;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('offloads the metrics broadcast to a queued job instead of computing inline', function () {
    Cache::flush();
    Queue::fake();

    $user = userWithTenant();
    $tenantId = (string) $user->tenant_id;

    DB::enableQueryLog();
    app(DashboardMetricsService::class)->dispatchUpdate($tenantId);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    Queue::assertPushed(
        ComputeDashboardMetricsJob::class,
        fn ($job) => $job->tenantId === $tenantId
    );

    // SCALE-3: the firing send/inbound worker must not run the tenant-wide
    // aggregate COUNTs inline — that cost belongs on the job, not the hot path.
    expect($queries)->toBeEmpty();
});

it('queues the metrics job on the default queue, off the hot send/inbound queues', function () {
    Cache::flush();
    Queue::fake();

    $user = userWithTenant();

    app(DashboardMetricsService::class)->dispatchUpdate((string) $user->tenant_id);

    Queue::assertPushed(
        ComputeDashboardMetricsJob::class,
        fn ($job) => $job->queue === 'default'
    );
});

it('debounces the metrics job to once per window per tenant', function () {
    Cache::flush();
    Queue::fake();

    $user = userWithTenant();
    $tenantId = (string) $user->tenant_id;
    $service = app(DashboardMetricsService::class);

    $service->dispatchUpdate($tenantId);
    $service->dispatchUpdate($tenantId);

    Queue::assertPushed(ComputeDashboardMetricsJob::class, 1);
});

it('broadcasts the tenant snapshot when the metrics job runs', function () {
    Cache::flush();
    Event::fake([DashboardMetricsUpdated::class]);

    $user = userWithTenant();
    $tenantId = (string) $user->tenant_id;

    (new ComputeDashboardMetricsJob($tenantId))->handle(app(DashboardMetricsService::class));

    Event::assertDispatched(
        DashboardMetricsUpdated::class,
        fn ($e) => $e->tenantId === $tenantId
    );
});
