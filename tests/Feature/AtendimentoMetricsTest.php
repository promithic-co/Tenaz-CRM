<?php

use App\Models\ConversationSession;
use App\Models\Lead;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('snapshot exposes per-session atendimento counters', function () {
    $tenantId = 'tenant-metrics';

    $reengaged = Lead::factory()->create(['tenant_id' => $tenantId]);
    ConversationSession::factory()->forLead($reengaged)->open()->create([
        'number' => 1,
        'open_reason' => ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL,
        'opened_at' => now(),
    ]);

    $fresh = Lead::factory()->create(['tenant_id' => $tenantId]);
    ConversationSession::factory()->forLead($fresh)->open()->create([
        'number' => 1,
        'open_reason' => ConversationSession::OPEN_REASON_FIRST_CONTACT,
        'opened_at' => now(),
    ]);

    $closedLead = Lead::factory()->create(['tenant_id' => $tenantId]);
    ConversationSession::factory()->forLead($closedLead)->closed(ConversationSession::OUTCOME_CONVERTED)->create([
        'number' => 1,
        'opened_at' => now()->subHours(2),
        'closed_at' => now()->subHour(),
    ]);

    // A different tenant's session must not leak into the snapshot.
    $otherLead = Lead::factory()->create(['tenant_id' => 'tenant-other']);
    ConversationSession::factory()->forLead($otherLead)->open()->create(['number' => 1]);

    // The snapshot is cached for 5s; earlier debounced recomputes (fired by factory-created
    // leads under the sync queue) may hold a pre-session value. Flush so this read is fresh.
    Cache::flush();

    $atendimentos = app(DashboardMetricsService::class)->snapshot($tenantId)['atendimentos'];

    expect($atendimentos['opened_today'])->toBe(3)
        ->and($atendimentos['reengaged_today'])->toBe(1)
        ->and($atendimentos['open_now'])->toBe(2)
        ->and($atendimentos['closed_7d'])->toBe(1)
        ->and($atendimentos['avg_close_minutes'])->toBe(60.0)
        ->and($atendimentos['outcomes_7d'])->toBe([ConversationSession::OUTCOME_CONVERTED => 1]);
});
