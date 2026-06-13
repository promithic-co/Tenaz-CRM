<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\FollowUpWindowService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function guardLead(string $stage, array $extra = []): Lead
{
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    return Lead::factory()->forAgent($agent)->create(array_merge([
        'tenant_id' => $user->tenantId,
        'operational_stage' => $stage,
        'followup_status' => 'active',
        'last_inbound_at' => now()->subMinutes(5),
    ], $extra));
}

function evaluateLead(Lead $lead): array
{
    return app(FollowUpWindowService::class)->evaluate($lead, [
        'enabled' => true,
        'max_attempts_within_window' => 5,
        'first_delay_minutes' => 1,
        'min_interval_minutes' => 1,
        'business_window_start' => '00:00',
        'business_window_end' => '23:59',
        'timezone' => 'UTC',
    ]);
}

test('human_pending blocks follow-up with reason human_paused', function () {
    $lead = guardLead(Lead::STAGE_HUMAN_PENDING);
    $result = evaluateLead($lead);

    expect($result['eligible'])->toBeFalse();
    expect($result['reason'])->toBe('human_paused');
});

test('human_active blocks follow-up with reason human_paused', function () {
    $lead = guardLead(Lead::STAGE_HUMAN_ACTIVE);
    $result = evaluateLead($lead);

    expect($result['eligible'])->toBeFalse();
    expect($result['reason'])->toBe('human_paused');
});

test('waiting_customer blocks follow-up with reason human_paused', function () {
    $lead = guardLead(Lead::STAGE_WAITING_CUSTOMER);
    $result = evaluateLead($lead);

    expect($result['eligible'])->toBeFalse();
    expect($result['reason'])->toBe('human_paused');
});

test('active AI pause blocks follow-up with reason human_paused', function () {
    $lead = guardLead(Lead::STAGE_AI_QUALIFYING, [
        'ai_paused_until' => now()->addHours(2),
    ]);
    $result = evaluateLead($lead);

    expect($result['eligible'])->toBeFalse();
    expect($result['reason'])->toBe('human_paused');
});

test('no_credit ticket alone does not block follow-up as human handoff', function () {
    $lead = guardLead(Lead::STAGE_AI_QUALIFYING);

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_NO_CREDIT,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $result = evaluateLead($lead);

    expect($result['reason'])->not->toBe('human_paused');
});

test('ai_qualifying with no pause is eligible', function () {
    $lead = guardLead(Lead::STAGE_AI_QUALIFYING);
    $result = evaluateLead($lead);

    expect($result['eligible'])->toBeTrue();
    expect($result['reason'])->toBe('eligible');
});
