<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\AgentInteractionEvent;
use App\Models\ConversationSession;
use App\Models\FollowupMessage;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversationHistoryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @return array{0: Tenant, 1: User, 2: Lead}
 */
function panelHistorySetup(TenantRole $role = TenantRole::Owner): array
{
    $tenant = Tenant::create(['name' => 'PanelHistoryTest']);

    $actor = User::factory()->create();
    $actor->tenants()->detach();
    $actor->tenants()->attach($tenant->id, ['role' => $role->value]);

    $agent = Agent::factory()->create([
        'user_id' => $actor->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'assigned_user_id' => $actor->id,
    ]);

    return [$tenant, $actor, $lead];
}

function recordHistoryEvent(Lead $lead, string $type, string $severity = 'info', ?array $payload = null, ?string $at = null): AgentInteractionEvent
{
    return AgentInteractionEvent::create([
        'interaction_id' => (string) Str::uuid(),
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'agent_id' => $lead->agent_id,
        'event_type' => $type,
        'event_source' => 'test',
        'severity' => $severity,
        'payload_json' => $payload,
        'created_at' => $at ?? now(),
    ]);
}

test('the history merges all four sources into one chronological list', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    ConversationSession::factory()->forLead($lead)->create([
        'number' => 1,
        'status' => ConversationSession::STATUS_CLOSED,
        'open_reason' => ConversationSession::OPEN_REASON_FIRST_CONTACT,
        'outcome' => ConversationSession::OUTCOME_CONVERTED,
        'opened_at' => now()->subDays(5),
        'closed_at' => now()->subDays(4),
    ]);

    FollowupMessage::factory()->create([
        'lead_id' => $lead->id,
        'attempt' => 2,
        'message_text' => 'Passando para saber se ainda tem interesse',
        'sent_at' => now()->subDays(3),
    ]);

    ServiceTicket::create([
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_RESOLVED,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'resolved_at' => now()->subDays(2),
        'resolution_reason' => ServiceTicket::RESOLUTION_CONVERTED,
    ]);

    recordHistoryEvent($lead, 'ai_paused_manual', at: now()->subDay()->toDateTimeString());

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);
    $kinds = array_column($history['entries'], 'kind');
    $timestamps = array_column($history['entries'], 'at');
    $sorted = $timestamps;
    rsort($sorted);

    expect($kinds)->toContain('event', 'session', 'ticket', 'followup')
        // Newest first: the panel reads top-down.
        ->and($timestamps)->toBe($sorted)
        ->and($history['truncated'])->toBeFalse();
});

test('the panel exposes the merged history with the retention window', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    recordHistoryEvent($lead, 'ai_paused_manual');

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activeConversation.history.entries', 1)
            ->where('activeConversation.history.entries.0.label', 'IA pausada')
            ->where('activeConversation.history.entries.0.kind', 'event')
            ->where('activeConversation.history.truncated', false)
            ->where(
                'activeConversation.history.event_retention_days',
                (int) config('laboratory.retention.interaction_events_days', 90),
            )
        );
});

/**
 * The old panel whitelisted seven event types. Widening that list is the point of
 * the merge, so the types an operator cares about must actually come through.
 */
test('the widened whitelist carries the events the old panel dropped', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    foreach (['outbound_failed', 'fact_check_failed', 'handoff_created', 'tool_called', 'agent_failed'] as $type) {
        recordHistoryEvent($lead, $type);
    }

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect(array_column($history['entries'], 'type'))
        ->toContain('outbound_failed', 'fact_check_failed', 'handoff_created', 'tool_called', 'agent_failed');
});

/**
 * Every whitelisted type is labelled by construction — the query filters on the
 * label map's own keys — so an unlabelled entry is impossible rather than merely
 * unlikely. This locks that property in place.
 */
test('every event that reaches the history carries a human label', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    foreach (['ai_resumed_manual', 'followup_failed', 'campaign_dispatch_failed', 'ura_inbound_received'] as $type) {
        recordHistoryEvent($lead, $type);
    }

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect($history['entries'])->toHaveCount(4);

    foreach ($history['entries'] as $entry) {
        expect($entry['label'])->not->toBe($entry['type'])
            ->and(trim($entry['label']))->not->toBe('');
    }
});

/**
 * The thread already shows every message, and the per-turn agent internals belong
 * to the Laboratory. Repeating them here would bury the four or five lines that
 * actually explain the conversation.
 */
test('per-message and per-turn plumbing stays out of the history', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    foreach ([
        'inbound_received',
        'outbound_queued',
        'outbound_sent',
        'conversation_persisted',
        'model_called',
        'agent_started',
        'agent_response_ready',
        'fact_check_passed',
        'webhook_received',
        'context_synced',
    ] as $type) {
        recordHistoryEvent($lead, $type);
    }

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect($history['entries'])->toBe([]);
});

/**
 * `followup_started` fires at the same moment the follow-up message row is written,
 * and only the row carries the text that was sent.
 */
test('followup_started is dropped in favour of the message row', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    recordHistoryEvent($lead, 'followup_started');
    FollowupMessage::factory()->create([
        'lead_id' => $lead->id,
        'attempt' => 1,
        'message_text' => 'Oi, tudo bem?',
        'sent_at' => now(),
    ]);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect($history['entries'])->toHaveCount(1)
        ->and($history['entries'][0]['type'])->toBe('followup_sent')
        ->and($history['entries'][0]['label'])->toBe('Follow-up #1 enviado')
        ->and($history['entries'][0]['detail'])->toBe('Oi, tudo bem?');
});

test('an atendimento contributes both its opening and its closing', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    ConversationSession::factory()->forLead($lead)->create([
        'number' => 3,
        'status' => ConversationSession::STATUS_CLOSED,
        'open_reason' => ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_INACTIVITY,
        'outcome' => ConversationSession::OUTCOME_LOST,
        'opened_at' => now()->subHours(4),
        'closed_at' => now()->subHour(),
    ]);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);
    $byType = collect($history['entries'])->keyBy('type');

    expect($history['entries'])->toHaveCount(2)
        ->and($byType['session_closed']['label'])->toBe('Atendimento #3 encerrado')
        ->and($byType['session_closed']['detail'])->toBe('perdido')
        ->and($byType['session_closed']['severity'])->toBe('warning')
        ->and($byType['session_opened']['label'])->toBe('Atendimento #3 aberto')
        ->and($byType['session_opened']['detail'])->toBe('retorno após inatividade');
});

test('an open atendimento contributes only its opening', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    ConversationSession::factory()->forLead($lead)->create([
        'number' => 1,
        'status' => ConversationSession::STATUS_OPEN,
        'opened_at' => now()->subHour(),
        'closed_at' => null,
    ]);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect($history['entries'])->toHaveCount(1)
        ->and($history['entries'][0]['type'])->toBe('session_opened');
});

test('a converted atendimento reads as success', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    ConversationSession::factory()->forLead($lead)->create([
        'number' => 1,
        'status' => ConversationSession::STATUS_CLOSED,
        'outcome' => ConversationSession::OUTCOME_CONVERTED,
        'opened_at' => now()->subHours(2),
        'closed_at' => now(),
    ]);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect(collect($history['entries'])->firstWhere('type', 'session_closed')['severity'])
        ->toBe('success');
});

/**
 * A ticket's creation and claim already arrive as handoff_created / handoff_claimed
 * events; deriving them from the ticket too would print every escalation twice.
 */
test('tickets contribute only their resolution, never a duplicate opening', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    recordHistoryEvent($lead, 'handoff_created', at: now()->subHours(3)->toDateTimeString());

    ServiceTicket::create([
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_RESOLVED,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'claimed_at' => now()->subHours(2),
        'resolved_at' => now()->subHour(),
        'resolution_reason' => ServiceTicket::RESOLUTION_CONVERTED,
    ]);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);
    $types = array_column($history['entries'], 'type');

    expect($types)->toBe(['ticket_resolved', 'handoff_created'])
        ->and(collect($history['entries'])->firstWhere('type', 'ticket_resolved'))
        ->toMatchArray([
            'label' => 'Atendimento humano resolvido',
            'detail' => 'convertido',
            'severity' => 'success',
        ]);
});

test('a ticket closed without a resolution is called out', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    ServiceTicket::create([
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_CLOSED,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'closed_at' => now()->subHour(),
        'resolved_at' => null,
    ]);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect($history['entries'])->toHaveCount(1)
        ->and($history['entries'][0]['type'])->toBe('ticket_closed')
        ->and($history['entries'][0]['severity'])->toBe('warning');
});

test('an event payload supplies the entry detail', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    recordHistoryEvent($lead, 'outbound_failed', 'error', ['reason' => 'Número inválido']);
    recordHistoryEvent($lead, 'tool_called', payload: ['tool' => 'consultar_credito_inss']);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);
    $byType = collect($history['entries'])->keyBy('type');

    expect($byType['outbound_failed']['detail'])->toBe('Número inválido')
        ->and($byType['outbound_failed']['severity'])->toBe('error')
        ->and($byType['tool_called']['detail'])->toBe('consultar_credito_inss');
});

test('a payload with nothing quotable yields no detail', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    recordHistoryEvent($lead, 'ai_paused_manual', payload: ['lead_id' => 7, 'nested' => ['a' => 'b']]);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect($history['entries'][0]['detail'])->toBeNull();
});

test('a long detail is truncated instead of flooding the panel', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    FollowupMessage::factory()->create([
        'lead_id' => $lead->id,
        'attempt' => 1,
        'message_text' => str_repeat('a', 400),
        'sent_at' => now(),
    ]);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect(mb_strlen((string) $history['entries'][0]['detail']))->toBeLessThanOrEqual(140)
        ->and($history['entries'][0]['detail'])->toEndWith('…');
});

/**
 * `critical` collapses into `error`: the panel is not a pager, and a second shade
 * of red buys the operator nothing.
 */
test('critical severity is folded into error', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    recordHistoryEvent($lead, 'agent_failed', 'critical');

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect($history['entries'][0]['severity'])->toBe('error');
});

test('the list is capped and reports that it was cut', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    foreach (range(1, ConversationHistoryBuilder::MAX_ENTRIES + 5) as $i) {
        recordHistoryEvent($lead, 'ai_paused_manual', at: now()->subMinutes($i)->toDateTimeString());
    }

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect($history['entries'])->toHaveCount(ConversationHistoryBuilder::MAX_ENTRIES)
        ->and($history['truncated'])->toBeTrue();
});

test('another lead history never leaks into this one', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    $otherLead = Lead::factory()->forAgent($lead->agent)->create([
        'tenant_id' => (string) $tenant->id,
    ]);

    recordHistoryEvent($otherLead, 'ai_paused_manual');
    FollowupMessage::factory()->create(['lead_id' => $otherLead->id, 'sent_at' => now()]);
    ConversationSession::factory()->forLead($otherLead)->create(['number' => 1]);

    $history = app(ConversationHistoryBuilder::class)->forLead($lead);

    expect($history['entries'])->toBe([]);
});

test('a lead with nothing recorded yields an empty history', function (): void {
    [$tenant, $owner, $lead] = panelHistorySetup();

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activeConversation.history.entries', 0)
            ->where('activeConversation.history.truncated', false)
        );
});

test('a restricted member sees the history of a lead assigned to them', function (): void {
    [$tenant, $member, $lead] = panelHistorySetup(TenantRole::User);

    recordHistoryEvent($lead, 'handoff_claimed');

    $this->actingAs($member)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activeConversation.history.entries', 1)
            ->where('activeConversation.history.entries.0.label', 'Atendimento assumido')
        );
});
