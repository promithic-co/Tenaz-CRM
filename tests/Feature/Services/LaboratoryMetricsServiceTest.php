<?php

use App\Models\AiRun;
use App\Services\LaboratoryMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function makeAiRun(string $tenantId, array $attributes): AiRun
{
    return AiRun::create(array_merge([
        'run_id' => (string) Str::uuid(),
        'trace_id' => (string) Str::uuid(),
        'tenant_id' => $tenantId,
        'agent_name' => 'CredFlowAgent',
        'model' => 'gpt-4o-mini',
        'started_at' => now(),
        'ended_at' => now(),
        'llm_calls' => 1,
        'tool_calls' => 2,
        'estimated_cost_usd' => 0.001,
        'status' => 'success',
    ], $attributes));
}

it('computes aiRunSummary aggregates in SQL without hydrating rows (GROW-2)', function () {
    $tenantId = 'lab-tenant';
    $service = app(LaboratoryMetricsService::class);

    makeAiRun($tenantId, ['duration_ms' => 100, 'status' => 'success', 'architecture_version' => 'a']);
    makeAiRun($tenantId, ['duration_ms' => 200, 'status' => 'success', 'architecture_version' => 'a']);
    makeAiRun($tenantId, ['duration_ms' => 300, 'status' => 'fallback', 'architecture_version' => 'b']);
    makeAiRun($tenantId, ['duration_ms' => 400, 'status' => 'error', 'architecture_version' => 'b']);
    // Another tenant's run must be excluded.
    makeAiRun('other-tenant', ['duration_ms' => 9999, 'status' => 'success', 'architecture_version' => 'a']);
    // Out-of-window run must be excluded.
    makeAiRun($tenantId, ['duration_ms' => 9999, 'status' => 'success', 'started_at' => now()->subDays(31)]);

    $summary = $service->aiRunSummary($tenantId);

    expect($summary['runs'])->toBe(4)
        ->and($summary['avg_cost_usd'])->toBe(0.001)
        ->and($summary['avg_latency_ms'])->toBe(250)
        ->and($summary['p95_latency_ms'])->toBe(400)
        ->and($summary['avg_llm_calls'])->toBe(1.0)
        ->and($summary['avg_tool_calls'])->toBe(2.0)
        ->and($summary['success_rate'])->toBe(50.0)
        ->and($summary['fallback_rate'])->toBe(25.0)
        ->and($summary['error_rate'])->toBe(25.0)
        ->and($summary['human_handoff_rate'])->toBe(0.0);
});

it('returns zeroed aiRunSummary when the tenant has no runs', function () {
    $summary = app(LaboratoryMetricsService::class)->aiRunSummary('empty-tenant');

    expect($summary['runs'])->toBe(0)
        ->and($summary['p95_latency_ms'])->toBe(0)
        ->and($summary['success_rate'])->toBe(0.0);
});

it('computes architectureComparison per version in SQL (GROW-2)', function () {
    $tenantId = 'lab-tenant';
    $service = app(LaboratoryMetricsService::class);

    makeAiRun($tenantId, ['duration_ms' => 100, 'status' => 'success', 'architecture_version' => 'a']);
    makeAiRun($tenantId, ['duration_ms' => 200, 'status' => 'success', 'architecture_version' => 'a']);
    makeAiRun($tenantId, ['duration_ms' => 300, 'status' => 'fallback', 'architecture_version' => 'b']);
    makeAiRun($tenantId, ['duration_ms' => 400, 'status' => 'error', 'architecture_version' => 'b']);

    $byVersion = collect($service->architectureComparison($tenantId))->keyBy('architecture_version');

    expect($byVersion)->toHaveCount(2);

    expect($byVersion['a'])->toMatchArray([
        'architecture_version' => 'a',
        'runs' => 2,
        'avg_latency_ms' => 150,
        'p95_latency_ms' => 200,
        'success_rate' => 100.0,
    ]);

    expect($byVersion['b'])->toMatchArray([
        'architecture_version' => 'b',
        'runs' => 2,
        'avg_latency_ms' => 350,
        'p95_latency_ms' => 400,
        'success_rate' => 0.0,
    ]);
});
