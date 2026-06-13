<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function queueTenant(): array
{
    $tenant = Tenant::create(['name' => 'QueueTest']);
    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id, 'is_default' => true]);

    return [$tenant, $owner, $agent];
}

function queueLead(Agent $agent, array $extra = []): Lead
{
    return Lead::factory()->forAgent($agent)->create(array_merge([
        'tenant_id' => (string) $agent->tenant_id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
    ], $extra));
}

function queueTicket(Lead $lead, array $extra = []): ServiceTicket
{
    return ServiceTicket::create(array_merge([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'sla_due_at' => now()->addHours(4),
    ], $extra));
}

test('index returns four bucket keys', function () {
    [$tenant, $owner] = queueTenant();

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->has('buckets.waiting')
            ->has('buckets.mine')
            ->has('buckets.ai')
            ->has('buckets.closed')
        );
});

test('index returns counter keys', function () {
    [$tenant, $owner] = queueTenant();

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertInertia(fn ($page) => $page
            ->has('counters.waiting')
            ->has('counters.mine')
            ->has('counters.ai')
            ->has('counters.closed')
            ->has('counters.overdue')
        );
});

test('waiting bucket includes open unassigned escalation ticket', function () {
    [$tenant, $owner, $agent] = queueTenant();
    $lead = queueLead($agent);
    queueTicket($lead);

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('counters.waiting', 1)
            ->has('buckets.waiting.data', 1)
        );
});

test('mine bucket includes ticket assigned to current user', function () {
    [$tenant, $owner, $agent] = queueTenant();
    $lead = queueLead($agent);
    queueTicket($lead, [
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('counters.mine', 1)
            ->has('buckets.mine.data', 1)
        );
});

test('ai bucket includes lead without active escalation in AI stage', function () {
    [$tenant, $owner, $agent] = queueTenant();
    queueLead($agent, ['operational_stage' => Lead::STAGE_AI_QUALIFYING]);

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('counters.ai', fn ($v) => $v >= 1)
        );
});

test('ai bucket excludes lead with active escalation ticket', function () {
    [$tenant, $owner, $agent] = queueTenant();
    $lead = queueLead($agent, ['operational_stage' => Lead::STAGE_AI_QUALIFYING]);
    queueTicket($lead);

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('counters.ai', 0)
        );
});

test('closed bucket includes resolved ticket', function () {
    [$tenant, $owner, $agent] = queueTenant();
    $lead = queueLead($agent);
    queueTicket($lead, ['status' => ServiceTicket::STATUS_RESOLVED, 'resolved_at' => now()]);

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('counters.closed', 1)
            ->has('buckets.closed.data', 1)
        );
});

test('overdue counter counts tickets past sla_due_at', function () {
    [$tenant, $owner, $agent] = queueTenant();
    $lead = queueLead($agent);
    queueTicket($lead, ['sla_due_at' => now()->subHour()]);

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('counters.overdue', 1)
        );
});

test('motivo filter narrows waiting results', function () {
    [$tenant, $owner, $agent] = queueTenant();
    $lead = queueLead($agent);
    queueTicket($lead, ['reason' => 'proposta_aceita', 'summary' => 'Credito Novo confirmado']);

    $lead2 = queueLead($agent);
    queueTicket($lead2, ['reason' => 'solicitacao_cliente', 'summary' => 'Outro resumo']);

    $this->actingAs($owner)->get(route('atendimentos.index', ['motivo' => 'proposta_aceita']))
        ->assertInertia(fn ($page) => $page
            ->where('counters.waiting', 1)
        );
});
