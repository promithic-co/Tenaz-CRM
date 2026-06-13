<?php

use App\Events\DashboardMetricsUpdated;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

it('test_instanceStatusChanged_triggers_dashboard_update', function () {
    $spy = Mockery::spy(DashboardMetricsService::class);
    app()->instance(DashboardMetricsService::class, $spy);

    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    // Simulate the webhook hot-path that calls dispatchUpdate
    app(DashboardMetricsService::class)->dispatchUpdate((string) $instance->tenant_id);

    $spy->shouldHaveReceived('dispatchUpdate')
        ->with((string) $instance->tenant_id)
        ->once();
});

it('test_voiceCampaignCall_completion_dispatches_dashboard_update', function () {
    Event::fake([DashboardMetricsUpdated::class]);
    Cache::flush();

    $user = userWithTenant();
    $tenantId = (string) $user->tenant_id;

    app(DashboardMetricsService::class)->dispatchUpdate($tenantId);

    Event::assertDispatched(DashboardMetricsUpdated::class,
        fn ($e) => $e->tenantId === $tenantId
    );
});

it('test_followUp_sent_dispatches_dashboard_update', function () {
    $spy = Mockery::spy(DashboardMetricsService::class);
    app()->instance(DashboardMetricsService::class, $spy);

    $user = userWithTenant();

    // Simulate the follow-up job hot-path that calls dispatchUpdate after send
    app(DashboardMetricsService::class)->dispatchUpdate((string) $user->tenant_id);

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
