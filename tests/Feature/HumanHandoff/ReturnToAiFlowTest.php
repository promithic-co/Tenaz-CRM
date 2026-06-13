<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FollowUpWindowService;
use App\Services\ServiceTicketLifecycleService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function returnToAiSetup(): array
{
    $tenant = Tenant::create(['name' => 'ReturnToAiTest']);

    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create([
        'user_id' => $owner->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
        'followup_status' => 'paused',
        'ai_paused_until' => now()->addHours(10),
        'ai_paused_reason' => 'ticket_claimed',
        'whatsapp' => '5511999990002',
        'last_inbound_at' => now()->subMinutes(10),
    ]);

    $ticket = ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'assigned_user_id' => $owner->id,
        'claimed_at' => now()->subMinutes(20),
        'sla_due_at' => now()->addHours(4),
    ]);

    return [$tenant, $owner, $lead, $ticket];
}

test('returnToAi resolves ticket with returned_to_ai reason', function () {
    [$tenant, $owner, $lead, $ticket] = returnToAiSetup();

    $result = app(ServiceTicketLifecycleService::class)->returnToAi($ticket, $owner);

    expect($result->status)->toBe(ServiceTicket::STATUS_RESOLVED);
    expect($result->resolution_reason)->toBe(ServiceTicket::RESOLUTION_RETURNED_TO_AI);
    expect($result->resolved_at)->not->toBeNull();
});

test('returnToAi clears AI pause on lead', function () {
    [$tenant, $owner, $lead, $ticket] = returnToAiSetup();

    app(ServiceTicketLifecycleService::class)->returnToAi($ticket->fresh(['lead']), $owner);

    $lead->refresh();
    expect($lead->ai_paused_until)->toBeNull();
    expect($lead->ai_paused_reason)->toBeNull();
    expect($lead->operational_stage)->toBe(Lead::STAGE_AI_QUALIFYING);
});

test('returnToAi removes lead assignment so IA takes over', function () {
    [$tenant, $owner, $lead, $ticket] = returnToAiSetup();

    app(ServiceTicketLifecycleService::class)->returnToAi($ticket->fresh(['lead']), $owner);

    $lead->refresh();
    expect($lead->assigned_user_id)->toBeNull();
});

test('follow-up is eligible after returnToAi within customer window', function () {
    [$tenant, $owner, $lead, $ticket] = returnToAiSetup();

    app(ServiceTicketLifecycleService::class)->returnToAi($ticket->fresh(['lead']), $owner);

    $lead->refresh();

    $result = app(FollowUpWindowService::class)->evaluate($lead, [
        'enabled' => true,
        'max_attempts_within_window' => 5,
        'first_delay_minutes' => 1,
        'min_interval_minutes' => 1,
        'business_window_start' => '00:00',
        'business_window_end' => '23:59',
        'timezone' => 'UTC',
    ]);

    expect($result['reason'])->not->toBe('human_paused');
});

test('no active escalation ticket after returnToAi', function () {
    [$tenant, $owner, $lead, $ticket] = returnToAiSetup();

    app(ServiceTicketLifecycleService::class)->returnToAi($ticket, $owner);

    $active = ServiceTicket::query()
        ->activeEscalation($lead->id)
        ->exists();

    expect($active)->toBeFalse();
});
