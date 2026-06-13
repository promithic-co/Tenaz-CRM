<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\PauseService;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('test_ticket_includes_urgency_for_open_tickets', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();

    $ticket = ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
        'reason' => 'Teste urgente',
    ]);

    // Backdate to simulate 13 hours ago
    $ticket->created_at = Carbon::now()->subHours(13);
    $ticket->save();

    $this->actingAs($user)
        ->get(route('atendimentos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->has('buckets.waiting.data.0', fn ($t) => $t
                ->where('urgency', 'high')
                ->where('hours_open', fn ($v) => $v >= 13)
                ->etc()
            )
        );
});

test('test_ticket_urgency_is_null_for_closed_tickets', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();

    ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_CLOSED,
        'reason' => 'Ticket fechado',
    ]);

    $this->actingAs($user)
        ->get(route('atendimentos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->has('buckets.closed.data.0', fn ($t) => $t
                ->where('urgency', null)
                ->where('hours_open', null)
                ->etc()
            )
        );
});

test('test_response_includes_open_count', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();

    // no_credit tickets are excluded from the escalation queue
    ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'no_credit',
        'status' => ServiceTicket::STATUS_OPEN,
        'reason' => 'Sem crédito disponível',
    ]);

    ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_CLOSED,
        'reason' => 'Encerrado',
    ]);

    $this->actingAs($user)
        ->get(route('atendimentos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->where('counters.waiting', 0)
            ->where('counters.closed', 1)
        );
});

test('open escalation ticket appears in waiting bucket', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();

    ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
        'reason' => 'proposta_aceita',
    ]);

    $this->actingAs($user)
        ->get(route('atendimentos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->where('counters.waiting', 1)
            ->has('buckets.waiting.data.0', fn ($t) => $t
                ->where('status', ServiceTicket::STATUS_OPEN)
                ->etc()
            )
        );
});

test('legacy aberto status alias is normalized on ticket creation', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();

    $ticket = ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => 'aberto',
        'reason' => 'solicitacao_cliente',
    ]);

    // The mutator normalizes 'aberto' → 'open' on save
    expect($ticket->fresh()->status)->toBe(ServiceTicket::STATUS_OPEN);

    $this->actingAs($user)
        ->get(route('atendimentos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->where('counters.waiting', 1)
        );
});

test('operator can claim an open ticket and ai is paused', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create(['whatsapp' => '5511999990000']);
    $ticket = ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $this->actingAs($user)
        ->post(route('atendimentos.claim', $ticket))
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(ServiceTicket::STATUS_ASSIGNED)
        ->and($ticket->assigned_user_id)->toBe($user->id)
        ->and($ticket->claimed_at)->not->toBeNull()
        ->and(app(PauseService::class)->isPaused($lead->whatsapp, $lead->tenant_id))->toBeTrue();
});

test('operator can resolve an active ticket', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'status' => 'escalado',
        'followup_status' => 'inactive',
    ]);
    $ticket = ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('atendimentos.resolve', $ticket), [
            'resolution_reason' => 'convertido',
            'resolution_notes' => 'Cliente encaminhado para formalizacao.',
        ])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(ServiceTicket::STATUS_RESOLVED)
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->resolution_reason)->toBe('convertido')
        ->and($lead->fresh()->status)->toBe('convertido')
        ->and($lead->fresh()->followup_status)->toBe('inactive');
});

test('atendimentos index exposes lead follow-up state', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'followup_status' => 'active',
        'followup_count' => 2,
    ]);

    ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $this->actingAs($user)
        ->get(route('atendimentos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->where('buckets.waiting.data.0.lead_followup_status', 'active')
            ->where('buckets.waiting.data.0.lead_followup_count', 2)
            ->where('buckets.waiting.data.0.lead_status', $lead->status)
        );
});

test('operator can disable follow-up from atendimento ticket', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'followup_status' => 'active',
        'followup_count' => 3,
    ]);
    $ticket = ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $this->actingAs($user)
        ->post(route('atendimentos.followup.disable', $ticket))
        ->assertRedirect()
        ->assertSessionHas('flash', 'Follow-up desligado para este lead.');

    $lead->refresh();
    expect($lead->followup_status)->toBe('inactive')
        ->and($lead->followup_count)->toBe(3);
});

foreach (['active', 'paused', 'inactive', 'legacy_active'] as $followupStatus) {
    test("disable follow-up accepts existing production-like status {$followupStatus}", function () use ($followupStatus) {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $lead = Lead::factory()->forAgent($agent)->create([
            'followup_status' => $followupStatus,
            'followup_count' => 4,
        ]);
        $ticket = ServiceTicket::create([
            'tenant_id' => $user->tenantId,
            'lead_id' => $lead->id,
            'type' => 'escalation',
            'status' => ServiceTicket::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->post(route('atendimentos.followup.disable', $ticket))
            ->assertRedirect()
            ->assertSessionHas('flash');

        $lead->refresh();
        expect($lead->followup_status)->toBe('inactive')
            ->and($lead->followup_count)->toBe(4);
    });
}

test('disable follow-up does not mutate lead business fields', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lastInteractionAt = now()->subHours(3)->startOfMinute();
    $lastInboundAt = now()->subHours(4)->startOfMinute();
    $conversationId = '12345678-1234-1234-1234-123456789012';
    $creditoJson = ['status' => 'QUALIFICADO', 'valor' => '1000.00'];

    $lead = Lead::factory()->forAgent($agent)->create([
        'status' => 'qualificado',
        'conversation_id' => $conversationId,
        'credito_json' => $creditoJson,
        'last_interaction_at' => $lastInteractionAt,
        'last_inbound_at' => $lastInboundAt,
        'followup_status' => 'active',
        'followup_count' => 2,
    ]);
    $ticket = ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $this->actingAs($user)
        ->post(route('atendimentos.followup.disable', $ticket))
        ->assertRedirect();

    $lead->refresh();
    expect($lead->followup_status)->toBe('inactive')
        ->and($lead->followup_count)->toBe(2)
        ->and($lead->status)->toBe('qualificado')
        ->and($lead->conversation_id)->toBe($conversationId)
        ->and($lead->credito_json)->toBe($creditoJson)
        ->and($lead->last_interaction_at->equalTo($lastInteractionAt))->toBeTrue()
        ->and($lead->last_inbound_at->equalTo($lastInboundAt))->toBeTrue();
});

test('counters reflect total not the current page slice', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    // 20 open/unassigned escalation tickets → waiting bucket paginates at 15/page.
    for ($i = 0; $i < 20; $i++) {
        $lead = Lead::factory()->forAgent($agent)->create();
        ServiceTicket::create([
            'tenant_id' => $user->tenantId,
            'lead_id' => $lead->id,
            'type' => 'escalation',
            'status' => ServiceTicket::STATUS_OPEN,
            'reason' => "ticket {$i}",
        ]);
    }

    $this->actingAs($user)
        ->get(route('atendimentos.index', ['waiting_page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            // page 2 holds only the 5 remaining rows
            ->has('buckets.waiting.data', 5)
            // counter still reflects the full total, not the page slice
            ->where('counters.waiting', 20)
        );
});

test('restricted user only sees own and unassigned escalation tickets', function () {
    $tenant = \App\Models\Tenant::create(['name' => 'TicketVisibility']);
    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => \App\Enums\TenantRole::Owner->value]);

    $restricted = User::factory()->create();
    $restricted->tenants()->detach();
    $restricted->tenants()->attach($tenant->id, ['role' => \App\Enums\TenantRole::User->value]);

    expect($restricted->isRestrictedUser())->toBeTrue();

    $agent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create(['tenant_id' => (string) $tenant->id]);

    // Assigned to the restricted user → visible.
    ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $restricted->id,
        'reason' => 'mine',
    ]);

    // Assigned to the owner → hidden from the restricted user's "mine" bucket.
    $lead2 = Lead::factory()->forAgent($agent)->create(['tenant_id' => (string) $tenant->id]);
    ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead2->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $owner->id,
        'reason' => 'not mine',
    ]);

    // Unassigned open ticket → visible to restricted (waiting queue).
    $lead3 = Lead::factory()->forAgent($agent)->create(['tenant_id' => (string) $tenant->id]);
    ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead3->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
        'reason' => 'unassigned',
    ]);

    $this->actingAs($restricted)
        ->get(route('atendimentos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->where('counters.mine', 1)
            ->where('counters.waiting', 1)
        );
});

test('unauthorized user cannot disable follow-up through atendimento', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $userA->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => $userA->tenantId,
        'followup_status' => 'active',
    ]);
    $ticket = ServiceTicket::create([
        'tenant_id' => $userA->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $this->actingAs($userB)
        ->post(route('atendimentos.followup.disable', $ticket))
        ->assertNotFound();

    expect($lead->fresh()->followup_status)->toBe('active');
});
