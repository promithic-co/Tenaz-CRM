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

function concurrencyTenant(): array
{
    $tenant = Tenant::create(['name' => 'ConcurrencyTest']);

    $owner1 = User::factory()->create();
    $owner1->tenants()->detach();
    $owner1->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $owner2 = User::factory()->create();
    $owner2->tenants()->detach();
    $owner2->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create([
        'user_id' => $owner1->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_HUMAN_PENDING,
    ]);

    $ticket = ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'sla_due_at' => now()->addHours(4),
    ]);

    return [$tenant, $owner1, $owner2, $lead, $ticket];
}

test('first claim wins; second operator receives validation error', function () {
    [$tenant, $user1, $user2, $lead, $ticket] = concurrencyTenant();

    $lifecycle = app(ServiceTicketLifecycleService::class);

    $lifecycle->claim($ticket, $user1);

    expect(fn () => $lifecycle->claim($ticket, $user2))
        ->toThrow(ValidationException::class);

    $ticket->refresh();
    expect((int) $ticket->assigned_user_id)->toBe((int) $user1->id);
});

test('same user can re-claim own ticket (idempotent)', function () {
    [$tenant, $user1, , $lead, $ticket] = concurrencyTenant();

    $lifecycle = app(ServiceTicketLifecycleService::class);

    $lifecycle->claim($ticket, $user1);
    $result = $lifecycle->claim($ticket, $user1);

    expect((int) $result->assigned_user_id)->toBe((int) $user1->id);
});

test('claimByLead rejects second operator on same lead', function () {
    [$tenant, $user1, $user2, $lead] = concurrencyTenant();

    $lifecycle = app(ServiceTicketLifecycleService::class);

    $lead->update(['operational_stage' => Lead::STAGE_HUMAN_PENDING]);

    $lifecycle->claimByLead($lead, $user1);

    expect(fn () => $lifecycle->claimByLead($lead, $user2))
        ->toThrow(ValidationException::class);

    $lead->refresh();
    expect((int) $lead->assigned_user_id)->toBe((int) $user1->id);
});

test('resolved ticket cannot be claimed again', function () {
    [$tenant, $user1, $user2, $lead, $ticket] = concurrencyTenant();

    $lifecycle = app(ServiceTicketLifecycleService::class);
    $ticket->update(['status' => ServiceTicket::STATUS_RESOLVED]);

    expect(fn () => $lifecycle->claim($ticket, $user2))
        ->toThrow(ValidationException::class);
});
