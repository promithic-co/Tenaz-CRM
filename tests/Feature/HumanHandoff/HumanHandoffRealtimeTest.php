<?php

use App\Enums\TenantRole;
use App\Events\AtendimentoCountersUpdated;
use App\Events\HumanHandoffClaimed;
use App\Events\HumanHandoffCreated;
use App\Events\HumanHandoffResolved;
use App\Events\HumanHandoffReturnedToAi;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HumanHandoffTransferService;
use App\Services\ServiceTicketLifecycleService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function realtimeTenant(): array
{
    $tenant = Tenant::create(['name' => 'RealtimeTest']);
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
        'followup_status' => 'active',
        'whatsapp' => '5511900000001',
    ]);

    return [$tenant, $user, $lead];
}

// ── Event shape tests ────────────────────────────────────────────────────────

test('HumanHandoffCreated broadcasts on atendimentos channel', function () {
    $event = new HumanHandoffCreated(
        tenantId: 'tenant-1',
        ticketId: 1,
        leadId: 2,
        priority: ServiceTicket::PRIORITY_NORMAL,
        slaAt: now()->toIso8601String(),
        summaryExcerpt: 'Test summary',
    );

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($event->broadcastAs())->toBe('handoff.created');
});

test('HumanHandoffClaimed broadcasts on atendimentos and conversation channels', function () {
    $event = new HumanHandoffClaimed(
        tenantId: 'tenant-1',
        ticketId: 1,
        leadId: 2,
        assignedUserId: 3,
        assignedUserName: 'Operador',
    );

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(2);
    expect($event->broadcastAs())->toBe('handoff.claimed');
});

test('HumanHandoffResolved broadcasts on atendimentos and conversation channels', function () {
    $event = new HumanHandoffResolved(
        tenantId: 'tenant-1',
        ticketId: 1,
        leadId: 2,
        resolutionReason: 'converted',
    );

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(2);
    expect($event->broadcastAs())->toBe('handoff.resolved');
});

test('HumanHandoffReturnedToAi broadcasts on atendimentos and conversation channels', function () {
    $event = new HumanHandoffReturnedToAi(
        tenantId: 'tenant-1',
        ticketId: 1,
        leadId: 2,
        aiMode: 'active',
    );

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(2);
    expect($event->broadcastAs())->toBe('handoff.returned_to_ai');
});

test('AtendimentoCountersUpdated broadcasts on atendimentos channel', function () {
    [, , $lead] = realtimeTenant();
    $event = new AtendimentoCountersUpdated((string) $lead->tenant_id);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($event->broadcastAs())->toBe('atendimento.counters.updated');
});

// ── Event dispatched by services ─────────────────────────────────────────────

test('transferFromAi dispatches HumanHandoffCreated and AtendimentoCountersUpdated', function () {
    Event::fake([HumanHandoffCreated::class, AtendimentoCountersUpdated::class]);
    [, , $lead] = realtimeTenant();

    app(HumanHandoffTransferService::class)->transferFromAi($lead, [
        'reason' => 'solicitacao_cliente',
        'summary' => 'Cliente quer proposta',
    ]);

    Event::assertDispatched(HumanHandoffCreated::class, fn ($e) => $e->leadId === $lead->id);
    Event::assertDispatched(AtendimentoCountersUpdated::class);
});

test('claim dispatches HumanHandoffClaimed and AtendimentoCountersUpdated', function () {
    Event::fake([HumanHandoffClaimed::class, AtendimentoCountersUpdated::class]);
    [$tenant, $user, $lead] = realtimeTenant();

    $ticket = ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'sla_due_at' => now()->addHours(4),
    ]);

    app(ServiceTicketLifecycleService::class)->claim($ticket, $user);

    Event::assertDispatched(HumanHandoffClaimed::class, fn ($e) => $e->ticketId === $ticket->id);
    Event::assertDispatched(AtendimentoCountersUpdated::class);
});

test('resolve dispatches HumanHandoffResolved and AtendimentoCountersUpdated', function () {
    Event::fake([HumanHandoffResolved::class, AtendimentoCountersUpdated::class]);
    [$tenant, $user, $lead] = realtimeTenant();

    $ticket = ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'sla_due_at' => now()->addHours(4),
    ]);

    app(ServiceTicketLifecycleService::class)->resolve($ticket, $user, 'converted');

    Event::assertDispatched(HumanHandoffResolved::class, fn ($e) => $e->ticketId === $ticket->id);
    Event::assertDispatched(AtendimentoCountersUpdated::class);
});

test('returnToAi dispatches HumanHandoffReturnedToAi and AtendimentoCountersUpdated', function () {
    Event::fake([HumanHandoffReturnedToAi::class, AtendimentoCountersUpdated::class]);
    [$tenant, $user, $lead] = realtimeTenant();

    $lead->update(['operational_stage' => Lead::STAGE_HUMAN_ACTIVE]);

    $ticket = ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $user->id,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'sla_due_at' => now()->addHours(4),
    ]);

    app(ServiceTicketLifecycleService::class)->returnToAi($ticket, $user);

    Event::assertDispatched(HumanHandoffReturnedToAi::class, fn ($e) => $e->ticketId === $ticket->id);
    Event::assertDispatched(AtendimentoCountersUpdated::class);
});
