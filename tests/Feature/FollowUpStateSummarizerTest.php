<?php

use App\Models\Agent;
use App\Models\AgentFollowUpSetting;
use App\Models\Lead;
use App\Services\FollowUpStateSummarizer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mid-day São Paulo — inside the default 08:00–20:00 business window.
    $this->travelTo(Carbon::parse('2026-03-16 13:00:00', 'America/Sao_Paulo'));
});

function summarize(Lead $lead): array
{
    return app(FollowUpStateSummarizer::class)->forLead($lead);
}

test('active eligible lead reports a next due time', function () {
    // count>0 anchors the interval on last_interaction_at; a 5h-old interaction with
    // the default 60min interval is comfortably past due, while last_inbound keeps the
    // WhatsApp window open. The wide margin makes the assertion timezone-robust.
    $lead = Lead::factory()->create([
        'is_sandbox' => false,
        'followup_status' => 'active',
        'followup_count' => 1,
        'last_inbound_at' => now()->subMinutes(10),
        'last_interaction_at' => now()->subHours(5),
    ]);

    $summary = summarize($lead);

    expect($summary['status'])->toBe('active')
        ->and($summary['reason'])->toBe('eligible')
        ->and($summary['next_due_at'])->not->toBeNull()
        ->and($summary['max'])->toBe(2);
});

test('inactive lead at the attempt ceiling infers max_reached', function () {
    $lead = Lead::factory()->create([
        'followup_status' => 'inactive',
        'followup_count' => 2,
        'last_inbound_at' => now()->subMinutes(10),
    ]);

    expect(summarize($lead)['reason'])->toBe('max_reached')
        ->and(summarize($lead)['reason_label'])->toBe('máximo de tentativas atingido');
});

test('inactive lead with a closed window infers window_expired', function () {
    $lead = Lead::factory()->create([
        'followup_status' => 'inactive',
        'followup_count' => 0,
        'last_inbound_at' => null,
        'free_entry_point_expires_at' => null,
    ]);

    expect(summarize($lead)['reason'])->toBe('window_expired');
});

test('inactive lead with follow-up disabled infers disabled', function () {
    $agent = Agent::factory()->create();
    AgentFollowUpSetting::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $agent->tenant_id,
        'enabled' => false,
    ]);

    $lead = Lead::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $agent->tenant_id,
        'followup_status' => 'inactive',
        'followup_count' => 0,
        'last_inbound_at' => now()->subMinutes(10),
    ]);

    expect(summarize($lead)['reason'])->toBe('disabled');
});

test('inactive lead inside the window with follow-up enabled infers manual', function () {
    $lead = Lead::factory()->create([
        'followup_status' => 'inactive',
        'followup_count' => 0,
        'last_inbound_at' => now()->subMinutes(10),
    ]);

    expect(summarize($lead)['reason'])->toBe('manual')
        ->and(summarize($lead)['reason_label'])->toBe('desativado manualmente');
});

test('paused lead reports the paused reason', function () {
    $lead = Lead::factory()->create([
        'followup_status' => 'paused',
        'followup_count' => 1,
        'last_inbound_at' => now()->subMinutes(10),
    ]);

    $summary = summarize($lead);

    expect($summary['reason'])->toBe('paused')
        ->and($summary['reason_label'])->toBe('pausado')
        ->and($summary['next_due_at'])->toBeNull();
});

test('lead without an agent falls back to tenant defaults for max', function () {
    $lead = Lead::factory()->create([
        'agent_id' => null,
        'followup_status' => 'active',
        'followup_count' => 0,
        'last_inbound_at' => now()->subMinutes(30),
    ]);

    expect(summarize($lead)['max'])->toBe(2);
});

test('forLeads resolves max per agent without querying settings per lead', function () {
    $agentA = Agent::factory()->create();
    AgentFollowUpSetting::factory()->create([
        'agent_id' => $agentA->id,
        'tenant_id' => $agentA->tenant_id,
        'max_attempts_within_window' => 3,
    ]);

    $agentB = Agent::factory()->create();
    AgentFollowUpSetting::factory()->create([
        'agent_id' => $agentB->id,
        'tenant_id' => $agentB->tenant_id,
        'max_attempts_within_window' => 5,
    ]);

    $leadA = Lead::factory()->create(['agent_id' => $agentA->id, 'tenant_id' => $agentA->tenant_id, 'followup_count' => 1]);
    $leadB = Lead::factory()->create(['agent_id' => $agentB->id, 'tenant_id' => $agentB->tenant_id, 'followup_count' => 4]);

    $out = app(FollowUpStateSummarizer::class)->forLeads(collect([$leadA, $leadB]));

    expect($out[$leadA->id]['max'])->toBe(3)
        ->and($out[$leadA->id]['count'])->toBe(1)
        ->and($out[$leadB->id]['max'])->toBe(5);
});
