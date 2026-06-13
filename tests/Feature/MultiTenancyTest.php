<?php

use App\Models\Agent;
use App\Models\AppSetting;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── User tenantId ───────────────────────────────────────────────────────────

test('user tenantId equals string of user id', function () {
    $user = User::factory()->create();

    expect($user->tenantId)->toBe((string) $user->id);
});

// ─── Lead scoping ────────────────────────────────────────────────────────────

test('user A cannot see leads from user B on dashboard', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Lead::factory()->forTenant($userA->tenantId)->create(['nome' => 'Lead de A']);
    Lead::factory()->forTenant($userB->tenantId)->create(['nome' => 'Lead de B']);

    $this->actingAs($userA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.total', 1)
        );
});

test('user A cannot see leads from user B in conversas', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $agentA = Agent::factory()->create(['user_id' => $userA->id, 'is_default' => true]);
    $agentB = Agent::factory()->create(['user_id' => $userB->id, 'is_default' => true]);

    Lead::factory()->forAgent($agentA)->create();
    Lead::factory()->forAgent($agentB)->create();

    $response = $this->actingAs($userA)->get(route('conversas.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.total', 1)
        );
});

test('user B cannot access lead belonging to user A', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $agentA = Agent::factory()->create(['user_id' => $userA->id, 'is_default' => true]);

    $lead = Lead::factory()->forAgent($agentA)->create();

    $this->actingAs($userB)
        ->get(route('conversas.show', $lead))
        ->assertNotFound();
});

test('user A can access their own lead', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk();
});

// ─── ServiceTicket scoping ────────────────────────────────────────────────────

test('service tickets are scoped by tenant', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $leadA = Lead::factory()->forTenant($userA->tenantId)->create();
    $leadB = Lead::factory()->forTenant($userB->tenantId)->create();

    ServiceTicket::create([
        'tenant_id' => $userA->tenantId,
        'lead_id' => $leadA->id,
        'type' => 'escalation',
    ]);

    // Same ticket type as tenant A's so only the tenant scope can exclude it.
    ServiceTicket::create([
        'tenant_id' => $userB->tenantId,
        'lead_id' => $leadB->id,
        'type' => 'escalation',
    ]);

    // Both tickets default to status "open" and are unassigned, so they land in
    // the "waiting" bucket. userA must see only their tenant's ticket.
    $this->actingAs($userA)
        ->get(route('atendimentos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->where('counters.waiting', 1)
        );
});

test('service ticket auto-inherits tenant_id from lead on create', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->forTenant($user->tenantId)->create();

    $ticket = ServiceTicket::create([
        'lead_id' => $lead->id,
        'type' => 'escalation',
    ]);

    expect($ticket->tenant_id)->toBe($user->tenantId);
});

// ─── AppSetting scoping ──────────────────────────────────────────────────────

test('app settings are isolated by user', function () {
    AppSetting::flushCache();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    AppSetting::set('agent_name', 'ARIA-A', $userA->id);
    AppSetting::set('agent_name', 'ARIA-B', $userB->id);

    expect(AppSetting::get('agent_name', null, $userA->id))->toBe('ARIA-A');
    expect(AppSetting::get('agent_name', null, $userB->id))->toBe('ARIA-B');
});

test('global settings serve as fallback when user has no custom setting', function () {
    AppSetting::flushCache();

    $user = User::factory()->create();

    AppSetting::set('agent_name', 'GLOBAL', null);

    expect(AppSetting::get('agent_name', 'DEFAULT', $user->id))->toBe('GLOBAL');
});

test('user specific setting overrides global fallback', function () {
    AppSetting::flushCache();

    $user = User::factory()->create();

    AppSetting::set('agent_name', 'GLOBAL', null);
    AppSetting::set('agent_name', 'CUSTOM', $user->id);

    expect(AppSetting::get('agent_name', 'DEFAULT', $user->id))->toBe('CUSTOM');
});

// ─── Playground scoping ──────────────────────────────────────────────────────

test('playground sessions are scoped by tenant', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Lead::factory()->forTenant($userA->tenantId)->sandbox()->create(['sandbox_label' => 'Sessão A']);
    Lead::factory()->forTenant($userB->tenantId)->sandbox()->create(['sandbox_label' => 'Sessão B']);

    $this->actingAs($userA)
        ->get(route('playground.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('playground/Index')
            ->where('sessions', fn ($sessions) => count($sessions) === 1)
        );
});

test('user B cannot access sandbox lead belonging to user A', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $lead = Lead::factory()->forTenant($userA->tenantId)->sandbox()->create();

    $this->actingAs($userB)
        ->postJson(route('playground.chat', $lead), ['message' => 'oi'])
        ->assertNotFound();
});

// ─── Clear lead history ──────────────────────────────────────────────────────

test('user can clear own lead conversation history and memory', function () {
    $user = User::factory()->create();
    $conversationId = Str::uuid()->toString();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('agent_conversation_messages')->insert([
        'id' => Str::uuid()->toString(),
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'aria',
        'role' => 'user',
        'content' => 'Hello',
        'attachments' => '',
        'tool_calls' => '',
        'tool_results' => '',
        'usage' => '',
        'meta' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $lead = Lead::factory()->forTenant($user->tenantId)->create([
        'conversation_id' => $conversationId,
    ]);

    $this->actingAs($user)
        ->post(route('conversas.clearHistory', $lead))
        ->assertRedirect();

    $lead->refresh();
    expect($lead->conversation_id)->toBeNull();
    expect(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(0);
    expect(DB::table('agent_conversations')->where('id', $conversationId)->count())->toBe(0);
});

test('user cannot clear history of lead from another tenant', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $lead = Lead::factory()->forTenant($userB->tenantId)->create();

    $this->actingAs($userA)
        ->post(route('conversas.clearHistory', $lead))
        ->assertNotFound();
});
