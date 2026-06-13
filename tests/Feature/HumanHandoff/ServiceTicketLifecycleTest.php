<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ServiceTicketLifecycleService;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function lifecycleTenant(): array
{
    $tenant = Tenant::create(['name' => 'LifecycleTest']);
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_default' => true]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_HUMAN_PENDING,
    ]);

    return [$tenant, $user, $lead];
}

function lifecycleSameTenantUser(Tenant $tenant): User
{
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    return $user;
}

test('claim locks and updates ticket and lead', function () {
    [, $user, $lead] = lifecycleTenant();

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $result = app(ServiceTicketLifecycleService::class)->claim($ticket, $user);

    expect($result->status)->toBe(ServiceTicket::STATUS_ASSIGNED);
    expect($result->assigned_user_id)->toBe($user->id);
    expect($result->claimed_at)->not->toBeNull();

    $lead->refresh();
    expect($lead->assigned_user_id)->toBe($user->id);
    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_ACTIVE);
});

test('claim through ServiceTicketController rejects second operator', function () {
    [$tenant, $owner, $lead] = lifecycleTenant();
    $secondUser = lifecycleSameTenantUser($tenant);

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $this->actingAs($owner)->post(route('atendimentos.claim', $ticket))->assertRedirect();

    $response = $this->actingAs($secondUser)->post(route('atendimentos.claim', $ticket));
    $response->assertRedirect();
    $response->assertSessionHasErrors();

    expect($ticket->fresh()->assigned_user_id)->toBe($owner->id);
});

test('claim rejects resolved ticket', function () {
    [, $user, $lead] = lifecycleTenant();

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_RESOLVED,
    ]);

    expect(fn () => app(ServiceTicketLifecycleService::class)->claim($ticket, $user))
        ->toThrow(ValidationException::class);
});

test('claim rejects closed ticket', function () {
    [, $user, $lead] = lifecycleTenant();

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_CLOSED,
    ]);

    expect(fn () => app(ServiceTicketLifecycleService::class)->claim($ticket, $user))
        ->toThrow(ValidationException::class);
});

test('mark human response sets first_response_at once and moves lead to waiting_customer', function () {
    [, $user, $lead] = lifecycleTenant();

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $user->id,
    ]);

    $lifecycle = app(ServiceTicketLifecycleService::class);
    $result = $lifecycle->markHumanResponse($lead, $user);

    expect($result->status)->toBe(ServiceTicket::STATUS_WAITING_CUSTOMER);
    expect($result->first_response_at)->not->toBeNull();
    expect($lead->fresh()->operational_stage)->toBe(Lead::STAGE_WAITING_CUSTOMER);

    $firstResponseAt = $result->first_response_at;
    $result2 = $lifecycle->markHumanResponse($lead, $user);
    expect($result2->first_response_at->toDateTimeString())->toBe($firstResponseAt->toDateTimeString());
});

test('resolve preserves history timestamps', function () {
    [, $user, $lead] = lifecycleTenant();

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $user->id,
        'claimed_at' => now()->subHour(),
    ]);

    $result = app(ServiceTicketLifecycleService::class)->resolve(
        $ticket,
        $user,
        ServiceTicket::RESOLUTION_RETURNED_TO_AI,
        'Client returning to AI queue'
    );

    expect($result->status)->toBe(ServiceTicket::STATUS_RESOLVED);
    expect($result->resolved_at)->not->toBeNull();
    expect($result->resolution_reason)->toBe(ServiceTicket::RESOLUTION_RETURNED_TO_AI);
    expect($result->claimed_at)->not->toBeNull();
});

test('return-to-AI resolution reason is stored correctly', function () {
    [, $user, $lead] = lifecycleTenant();

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
    ]);

    $result = app(ServiceTicketLifecycleService::class)->resolve(
        $ticket, $user, ServiceTicket::RESOLUTION_RETURNED_TO_AI
    );

    expect($result->resolution_reason)->toBe(ServiceTicket::RESOLUTION_RETURNED_TO_AI);
});
