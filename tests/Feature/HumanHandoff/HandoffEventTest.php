<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\AgentInteractionEvent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgentInteractionEventService;
use App\Services\HumanHandoffTransferService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function eventLead(): Lead
{
    $tenant = Tenant::create(['name' => 'EventTest']);
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_default' => true]);

    return Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
        'followup_status' => 'active',
        'status' => 'qualificado',
    ]);
}

test('handoff_created event is recorded with ticket id, lead id, reason and summary excerpt', function () {
    $lead = eventLead();

    app(HumanHandoffTransferService::class)->transferFromAi($lead, [
        'reason' => 'proposta_aceita',
        'summary' => 'Crédito Novo R$ 12500 aprovado para cliente INSS',
    ]);

    $event = AgentInteractionEvent::where('lead_id', $lead->id)
        ->where('event_type', 'handoff_created')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->event_source)->toBe('ai_tool');
    expect($event->payload_json)->toHaveKey('ticket_id');
    expect($event->payload_json)->toHaveKey('lead_id');
    expect($event->payload_json['reason'])->toBe('proposta_aceita');
    expect($event->payload_json['summary_excerpt'])->toContain('Crédito Novo');
});

test('event failure does not roll back ticket and lead state', function () {
    $lead = eventLead();

    // Bind a broken event service that always throws.
    $this->app->bind(AgentInteractionEventService::class, function () {
        $mock = Mockery::mock(AgentInteractionEventService::class);
        $mock->shouldReceive('record')->andThrow(new \RuntimeException('Event service down'));

        return $mock;
    });

    // Should not throw despite event failure.
    $ticket = app(HumanHandoffTransferService::class)->transferFromAi($lead, ['reason' => 'proposta_aceita']);

    expect($ticket)->toBeInstanceOf(ServiceTicket::class);
    expect($ticket->status)->toBe(ServiceTicket::STATUS_OPEN);
    expect($lead->fresh()->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);
});

test('event summary_excerpt is truncated to 120 chars', function () {
    $lead = eventLead();
    $longSummary = str_repeat('A', 200);

    app(HumanHandoffTransferService::class)->transferFromAi($lead, [
        'reason' => 'outro',
        'summary' => $longSummary,
    ]);

    $event = AgentInteractionEvent::where('lead_id', $lead->id)
        ->where('event_type', 'handoff_created')
        ->first();

    expect(mb_strlen($event->payload_json['summary_excerpt']))->toBeLessThanOrEqual(120);
});
