<?php

use App\Models\Agent;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\User;
use App\Services\ConversationTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('store opens a manual atendimento for the lead', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();

    $this->actingAs($user)
        ->post(route('conversas.sessions.store', $lead))
        ->assertRedirect();

    $session = ConversationSession::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail();
    expect($session->status)->toBe(ConversationSession::STATUS_OPEN)
        ->and($session->open_reason)->toBe(ConversationSession::OPEN_REASON_MANUAL);
});

test('store reuses the open session instead of opening a second', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    ConversationSession::factory()->forLead($lead)->open()->create();

    $this->actingAs($user)
        ->post(route('conversas.sessions.store', $lead))
        ->assertRedirect();

    expect(ConversationSession::withoutGlobalScopes()->where('lead_id', $lead->id)->count())->toBe(1);
});

test('close ends the atendimento with the selected outcome', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()->forLead($lead)->open()->create();

    $this->actingAs($user)
        ->post(route('conversas.sessions.close', [$lead, $session]), [
            'outcome' => ConversationSession::OUTCOME_CONVERTED,
        ])
        ->assertRedirect();

    expect($session->fresh()->status)->toBe(ConversationSession::STATUS_CLOSED)
        ->and($session->fresh()->outcome)->toBe(ConversationSession::OUTCOME_CONVERTED);
});

test('close rejects an invalid outcome', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()->forLead($lead)->open()->create();

    $this->actingAs($user)
        ->post(route('conversas.sessions.close', [$lead, $session]), ['outcome' => 'bogus'])
        ->assertSessionHasErrors('outcome');

    expect($session->fresh()->status)->toBe(ConversationSession::STATUS_OPEN);
});

test('close refuses a session that belongs to another lead', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $otherLead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()->forLead($otherLead)->open()->create();

    $this->actingAs($user)
        ->post(route('conversas.sessions.close', [$lead, $session]), [
            'outcome' => ConversationSession::OUTCOME_LOST,
        ])
        ->assertNotFound();

    expect($session->fresh()->status)->toBe(ConversationSession::STATUS_OPEN);
});

test('store is forbidden for a lead in another tenant', function () {
    $owner = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $owner->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('conversas.sessions.store', $lead))
        ->assertNotFound();
});

test('panel exposes the sessions list and per-message session id', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()->forLead($lead)->open()->create(['number' => 1]);

    app(ConversationTimelineService::class)->record(
        lead: $lead,
        direction: 'inbound',
        senderType: 'lead',
        body: 'oi',
        sessionId: $session->id,
    );

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('activeConversation.sessions.0.id', $session->id)
            ->where('activeConversation.sessions.0.number', 1)
            ->where('activeConversation.sessions.0.status', ConversationSession::STATUS_OPEN)
            ->where('activeConversation.mensagens.0.session_id', $session->id)
        );
});

test('inbox flags a returning lead', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    ConversationSession::factory()->forLead($lead)->open()->create([
        'open_reason' => ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL,
    ]);

    $this->actingAs($user)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.data.0.is_returning', true)
        );
});
