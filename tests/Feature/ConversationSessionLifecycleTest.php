<?php

use App\Events\ConversationSessionClosed;
use App\Events\ConversationSessionOpened;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\ConversationSessionLifecycleService;
use App\Services\ConversationTimelineService;
use App\Services\ServiceTicketLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function lifecycle(): ConversationSessionLifecycleService
{
    return app(ConversationSessionLifecycleService::class);
}

test('ensureOpenSession opens a first_contact session when the lead has none', function () {
    Event::fake([ConversationSessionOpened::class]);
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-l', 'status' => 'novo']);

    $session = lifecycle()->ensureOpenSession($lead);

    expect($session->status)->toBe(ConversationSession::STATUS_OPEN)
        ->and($session->number)->toBe(1)
        ->and($session->open_reason)->toBe(ConversationSession::OPEN_REASON_FIRST_CONTACT)
        ->and($session->wasRecentlyCreated)->toBeTrue();

    Event::assertDispatched(ConversationSessionOpened::class);
});

test('ensureOpenSession reuses the existing open session', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-l']);
    $first = lifecycle()->ensureOpenSession($lead);

    $second = lifecycle()->ensureOpenSession($lead);

    expect($second->id)->toBe($first->id)
        ->and($second->wasRecentlyCreated)->toBeFalse()
        ->and(ConversationSession::withoutGlobalScopes()->where('lead_id', $lead->id)->count())->toBe(1);
});

test('ensureOpenSession stamps campaign reason and campaign_id metadata on a new session', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-l', 'status' => 'novo']);

    $session = lifecycle()->ensureOpenSession($lead, ConversationSession::OPEN_REASON_CAMPAIGN, ['campaign_id' => 77]);

    expect($session->open_reason)->toBe(ConversationSession::OPEN_REASON_CAMPAIGN)
        ->and($session->metadata)->toBe(['campaign_id' => 77]);
});

test('ensureOpenSession does not overwrite an already-open session with campaign metadata', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-l', 'status' => 'novo']);
    $first = lifecycle()->ensureOpenSession($lead);

    $second = lifecycle()->ensureOpenSession($lead, ConversationSession::OPEN_REASON_CAMPAIGN, ['campaign_id' => 77]);

    expect($second->id)->toBe($first->id)
        ->and($second->open_reason)->toBe(ConversationSession::OPEN_REASON_FIRST_CONTACT)
        ->and($second->metadata)->toBeNull();
});

test('a returning inbound after a terminal status opens a reengagement session', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-l', 'status' => 'convertido']);
    ConversationSession::factory()->forLead($lead)->closed(ConversationSession::OUTCOME_CONVERTED)->create(['number' => 1]);

    $session = lifecycle()->ensureOpenSession($lead);

    expect($session->status)->toBe(ConversationSession::STATUS_OPEN)
        ->and($session->number)->toBe(2)
        ->and($session->open_reason)->toBe(ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL);
});

test('post-terminal guard parks the lead in the human queue and pauses AI', function () {
    $lead = Lead::factory()->create([
        'tenant_id' => 'tenant-l',
        'status' => 'convertido',
        'operational_stage' => Lead::STAGE_WON,
    ]);

    lifecycle()->applyPostTerminalGuard($lead);
    $lead->refresh();

    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING)
        ->and($lead->ai_paused_reason)->toBe('post_terminal_reengagement')
        ->and($lead->isAiPaused())->toBeTrue();
});

test('close is idempotent and broadcasts once', function () {
    Event::fake([ConversationSessionClosed::class]);
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-l']);
    $session = lifecycle()->ensureOpenSession($lead);

    lifecycle()->close($session, ConversationSession::OUTCOME_LOST);
    lifecycle()->close($session->fresh(), ConversationSession::OUTCOME_CONVERTED);

    $fresh = $session->fresh();
    expect($fresh->status)->toBe(ConversationSession::STATUS_CLOSED)
        ->and($fresh->outcome)->toBe(ConversationSession::OUTCOME_LOST);

    Event::assertDispatchedTimes(ConversationSessionClosed::class, 1);
});

test('timeline record auto-stamps the open session when no id is passed', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-l']);
    $session = lifecycle()->ensureOpenSession($lead);

    $message = app(ConversationTimelineService::class)->record(
        lead: $lead,
        direction: 'outbound',
        senderType: 'human',
        body: 'oi',
    );

    expect($message->session_id)->toBe($session->id);
});

test('a terminal lead status closes the open session via the listener', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-l', 'status' => 'novo']);
    $session = lifecycle()->ensureOpenSession($lead);

    $lead->update(['status' => 'convertido']);

    expect($session->fresh()->status)->toBe(ConversationSession::STATUS_CLOSED)
        ->and($session->fresh()->outcome)->toBe(ConversationSession::OUTCOME_CONVERTED);
});

test('resolving a ticket closes the leads open session', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['tenant_id' => (string) $user->tenantId, 'status' => 'escalado']);
    $session = lifecycle()->ensureOpenSession($lead);

    $ticket = ServiceTicket::create([
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'assigned_user_id' => $user->id,
    ]);

    app(ServiceTicketLifecycleService::class)->resolve($ticket, $user, 'convertido');

    expect($session->fresh()->status)->toBe(ConversationSession::STATUS_CLOSED)
        ->and($session->fresh()->outcome)->toBe(ConversationSession::OUTCOME_CONVERTED);
});

test('auto-close closes sessions idle past the window', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-l']);
    $stale = ConversationSession::factory()->forLead($lead)->open()->create([
        'last_message_at' => now()->subDays(ConversationSessionLifecycleService::AUTO_CLOSE_INACTIVITY_DAYS + 5),
    ]);

    $freshLead = Lead::factory()->create(['tenant_id' => 'tenant-l']);
    $recent = ConversationSession::factory()->forLead($freshLead)->open()->create([
        'last_message_at' => now()->subDay(),
    ]);

    $this->artisan('sessions:auto-close')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(ConversationSession::STATUS_CLOSED)
        ->and($stale->fresh()->outcome)->toBe(ConversationSession::OUTCOME_ABANDONED)
        ->and($recent->fresh()->status)->toBe(ConversationSession::STATUS_OPEN);
});
