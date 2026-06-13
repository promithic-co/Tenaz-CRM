<?php

use App\Models\Lead;
use App\Models\StatusMachine;
use App\Services\StatusMachineService;
use Illuminate\Support\Facades\DB;

// ─── Request-level cache ──────────────────────────────────────────────────────

it('10 canTransitionTo calls on the same tenant make only 1 status_machines query', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    // Pre-create a persisted machine so the cache actually hits the DB once
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    // Flush any cache from setup calls
    StatusMachine::flushCache($tenantId);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'novo',
    ]);

    $queryCount = 0;
    DB::listen(function ($query) use (&$queryCount): void {
        if (str_contains($query->sql, 'status_machines')) {
            $queryCount++;
        }
    });

    for ($i = 0; $i < 10; $i++) {
        $lead->canTransitionTo('qualificado');
    }

    expect($queryCount)->toBe(1);
});

it('forTenant returns the same instance on repeated calls within a request', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);
    StatusMachine::flushCache($tenantId);

    $first = StatusMachine::forTenant($tenantId);
    $second = StatusMachine::forTenant($tenantId);

    expect($first)->toBe($second);
});

it('observer flushes cache after save so next forTenant re-queries', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    $machine = app(StatusMachineService::class)->getOrCreateForTenant($tenantId);
    StatusMachine::flushCache($tenantId);

    // Prime cache
    StatusMachine::forTenant($tenantId);

    // Update machine — observer should flush cache
    app(StatusMachineService::class)->updateStatus($machine, 'novo', ['label' => 'Novo Editado']);

    // Now forTenant should NOT return cached stale value — it re-queries
    $fresh = StatusMachine::forTenant($tenantId);
    $status = $fresh->getStatuses()->firstWhere('slug', 'novo');

    expect($status['label'])->toBe('Novo Editado');
});
