<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversationTransferService;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function transferTenant(): array
{
    $tenant = Tenant::create(['name' => 'TransferTest']);
    $actor = User::factory()->create();
    $actor->tenants()->detach();
    $actor->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create(['user_id' => $actor->id, 'tenant_id' => $tenant->id, 'is_default' => true]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
        'followup_status' => 'active',
    ]);

    return [$tenant, $actor, $lead];
}

function transferSameTenantUser(Tenant $tenant): User
{
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    return $user;
}

test('transfer to user creates active escalation ticket when none exists', function () {
    [$tenant, $actor, $lead] = transferTenant();
    $target = transferSameTenantUser($tenant);

    app(ConversationTransferService::class)->transferToUser($lead, $actor, $target);

    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->type)->toBe(ServiceTicket::TYPE_ESCALATION);
    expect($ticket->assigned_user_id)->toBe($target->id);
});

test('transfer to user reuses active escalation ticket without duplication', function () {
    [$tenant, $actor, $lead] = transferTenant();
    $target = transferSameTenantUser($tenant);

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    app(ConversationTransferService::class)->transferToUser($lead, $actor, $target);

    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('transfer to user pauses AI and sets follow-up paused', function () {
    [$tenant, $actor, $lead] = transferTenant();
    $target = transferSameTenantUser($tenant);

    app(ConversationTransferService::class)->transferToUser($lead, $actor, $target);

    $lead->refresh();
    expect($lead->followup_status)->toBe('paused');
    expect($lead->ai_paused_until)->not->toBeNull();
    expect($lead->ai_paused_until->isFuture())->toBeTrue();
    expect($lead->ai_paused_reason)->toBe('conversation_transferred_to_user');
});

test('transfer to user sets ticket and lead assignee to same target user', function () {
    [$tenant, $actor, $lead] = transferTenant();
    $target = transferSameTenantUser($tenant);

    app(ConversationTransferService::class)->transferToUser($lead, $actor, $target);

    $lead->refresh();
    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();

    expect($lead->assigned_user_id)->toBe($target->id);
    expect($ticket->assigned_user_id)->toBe($target->id);
});

test('transfer to user sets lead operational_stage to human_active', function () {
    [$tenant, $actor, $lead] = transferTenant();
    $target = transferSameTenantUser($tenant);

    app(ConversationTransferService::class)->transferToUser($lead, $actor, $target);

    expect($lead->fresh()->operational_stage)->toBe(Lead::STAGE_HUMAN_ACTIVE);
});

test('same transfer retried for same target is idempotent', function () {
    [$tenant, $actor, $lead] = transferTenant();
    $target = transferSameTenantUser($tenant);

    app(ConversationTransferService::class)->transferToUser($lead, $actor, $target);
    app(ConversationTransferService::class)->transferToUser($lead, $actor, $target);

    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
    expect($lead->fresh()->assigned_user_id)->toBe($target->id);
});

test('transfer rejects target user from another tenant', function () {
    [, $actor, $lead] = transferTenant();
    $foreignUser = User::factory()->create(); // different tenant

    expect(fn () => app(ConversationTransferService::class)->transferToUser($lead, $actor, $foreignUser))
        ->toThrow(ValidationException::class);
});

test('transfer from AI-active lead prevents future automated follow-up eligibility', function () {
    [$tenant, $actor, $lead] = transferTenant();
    $target = transferSameTenantUser($tenant);
    $lead->update(['last_inbound_at' => now()->subMinutes(5)]);

    app(ConversationTransferService::class)->transferToUser($lead, $actor, $target);

    $lead->refresh();
    $result = app(\App\Services\FollowUpWindowService::class)->evaluate($lead, [
        'enabled' => true,
        'max_attempts_within_window' => 5,
        'first_delay_minutes' => 1,
        'min_interval_minutes' => 1,
        'business_window_start' => '00:00',
        'business_window_end' => '23:59',
    ]);

    expect($result['eligible'])->toBeFalse();
    // reason is 'not_active' — followup_status='paused' blocks before handoff stage check
});
