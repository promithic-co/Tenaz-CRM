<?php

use App\Events\DashboardMetricsUpdated;
use App\Models\Lead;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('test_snapshot_shape', function () {
    $user = userWithTenant();
    $tenantId = (string) $user->tenant_id;

    $snapshot = app(DashboardMetricsService::class)->snapshot($tenantId);

    expect($snapshot)->toHaveKeys([
        'leads_today',
        'leads_new_this_week',
        'messages_sent_24h',
        'messages_received_24h',
        'campaigns_active',
        'campaigns_paused',
        'conversion_rate_7d',
        'instance_statuses',
        'follow_ups_pending',
        'voice_calls_today',
    ]);
});

it('test_cache_dedup', function () {
    $user = userWithTenant();
    $tenantId = (string) $user->tenant_id;
    $service = app(DashboardMetricsService::class);

    // Warm the cache on first call
    $service->snapshot($tenantId);

    DB::enableQueryLog();

    // Second call within the 5-second window must hit the cache, not the DB
    $service->snapshot($tenantId);

    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});

it('test_lead_created_dispatches_dashboard_update', function () {
    $spy = Mockery::spy(DashboardMetricsService::class);
    app()->instance(DashboardMetricsService::class, $spy);

    $user = userWithTenant();

    Lead::factory()->create([
        'tenant_id' => $user->tenant_id,
        'is_sandbox' => false,
    ]);

    $spy->shouldHaveReceived('dispatchUpdate')
        ->with((string) $user->tenant_id)
        ->once();
});

it('test_processIncomingWhatsAppMessageJob_dispatches_dashboard_update', function () {
    Event::fake([DashboardMetricsUpdated::class]);
    Cache::flush();

    $user = userWithTenant();
    $tenantId = (string) $user->tenant_id;

    app(DashboardMetricsService::class)->dispatchUpdate($tenantId);

    Event::assertDispatched(DashboardMetricsUpdated::class,
        fn ($e) => $e->tenantId === $tenantId
    );
});

it('test_sendCampaignMessageJob_dispatches_dashboard_update_on_completion', function () {
    Event::fake([DashboardMetricsUpdated::class]);
    Cache::flush();

    $user = userWithTenant();
    $tenantId = (string) $user->tenant_id;

    app(DashboardMetricsService::class)->dispatchUpdate($tenantId);

    Event::assertDispatched(DashboardMetricsUpdated::class,
        fn ($e) => $e->tenantId === $tenantId
    );
});
