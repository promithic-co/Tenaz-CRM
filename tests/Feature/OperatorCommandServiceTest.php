<?php

use App\Enums\OperatorAction;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;
use App\Services\ConversationTimelineService;
use App\Services\OperatorCommandService;
use App\Services\PauseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeadWithConversation(Agent $agent, array $overrides = []): Lead
{
    $conversationId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'title' => 'Test conversation',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Lead::factory()->forAgent($agent)->create(array_merge([
        'conversation_id' => $conversationId,
    ], $overrides));
}

test('it auto-pauses when operator sends regular message', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $lead = createLeadWithConversation($agent);

    $service = app(OperatorCommandService::class);
    $pause = app(PauseService::class);

    $result = $service->handleOutgoingMessage(
        phone: $lead->whatsapp,
        tenantId: $lead->tenant_id,
        agentId: $agent->id,
        instanceName: 'test-instance',
        message: 'Olá, sou o operador humano!',
    );

    expect($result)->toBe(OperatorAction::Takeover);
    expect($pause->isPaused($lead->whatsapp, $lead->tenant_id))->toBeTrue();

    $stored = DB::table('conversation_timeline_messages')
        ->where('lead_id', $lead->id)
        ->where('sender_type', 'human')
        ->first();

    expect($stored)->not->toBeNull();
    expect($stored->body)->toBe('Olá, sou o operador humano!');
});

test('it resumes when operator sends #retomar', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $lead = createLeadWithConversation($agent);

    $pause = app(PauseService::class);
    $pause->pause($lead->whatsapp, $lead->tenant_id);

    $service = app(OperatorCommandService::class);

    $result = $service->handleOutgoingMessage(
        phone: $lead->whatsapp,
        tenantId: $lead->tenant_id,
        agentId: $agent->id,
        instanceName: 'test-instance',
        message: '#retomar',
    );

    expect($result)->toBe(OperatorAction::Command);
    expect($pause->isPaused($lead->whatsapp, $lead->tenant_id))->toBeFalse();

    $stored = DB::table('conversation_timeline_messages')
        ->where('lead_id', $lead->id)
        ->count();

    expect($stored)->toBe(0);
});

test('it handles #retomar case-insensitively', function (string $command) {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $lead = Lead::factory()->forAgent($agent)->create();

    $pause = app(PauseService::class);
    $pause->pause($lead->whatsapp, $lead->tenant_id);

    $service = app(OperatorCommandService::class);

    $result = $service->handleOutgoingMessage(
        phone: $lead->whatsapp,
        tenantId: $lead->tenant_id,
        agentId: $agent->id,
        instanceName: 'test-instance',
        message: $command,
    );

    expect($result)->toBe(OperatorAction::Command);
    expect($pause->isPaused($lead->whatsapp, $lead->tenant_id))->toBeFalse();
})->with([
    '#RETOMAR',
    '#Retomar',
    ' #retomar ',
]);

test('it does not reset pause TTL on subsequent operator messages', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $lead = createLeadWithConversation($agent);

    // Mock PauseService to track calls
    $pauseMock = Mockery::mock(PauseService::class);
    $pauseMock->shouldReceive('isPaused')->once()->andReturn(true);
    $pauseMock->shouldNotReceive('pause'); // Should NOT be called when already paused

    $service = new OperatorCommandService($pauseMock, app(ConversationTimelineService::class));

    $result = $service->handleOutgoingMessage(
        phone: $lead->whatsapp,
        tenantId: $lead->tenant_id,
        agentId: $agent->id,
        instanceName: 'test-instance',
        message: 'Outra mensagem do operador',
    );

    expect($result)->toBe(OperatorAction::Takeover);
});

test('it returns ignored when no lead exists', function () {
    $service = app(OperatorCommandService::class);

    $result = $service->handleOutgoingMessage(
        phone: '5511999999999',
        tenantId: 'nonexistent',
        agentId: null,
        instanceName: 'test-instance',
        message: 'Hello from operator',
    );

    expect($result)->toBe(OperatorAction::Ignored);
});

test('it stores operator message with media context', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $lead = createLeadWithConversation($agent);

    $service = app(OperatorCommandService::class);

    $media = [
        'type' => 'image',
        'url' => 'https://example.com/photo.jpg',
        'mimetype' => 'image/jpeg',
    ];

    $result = $service->handleOutgoingMessage(
        phone: $lead->whatsapp,
        tenantId: $lead->tenant_id,
        agentId: $agent->id,
        instanceName: 'test-instance',
        message: 'Veja esta foto',
        mediaContext: $media,
    );

    expect($result)->toBe(OperatorAction::Takeover);

    $stored = DB::table('conversation_timeline_messages')
        ->where('lead_id', $lead->id)
        ->where('sender_type', 'human')
        ->first();

    expect($stored)->not->toBeNull();
    expect($stored->media)->toContain('image');
});

test('it does not duplicate webhook fromMe message already recorded by outbox', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $lead = createLeadWithConversation($agent);

    DB::table('conversation_timeline_messages')->insert([
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $lead->conversation_id,
        'direction' => 'outbound',
        'sender_type' => 'human',
        'channel' => 'whatsapp',
        'body' => 'Mensagem via interface',
        'status' => 'sent',
        'source' => 'manual',
        'provider_message_id' => 'evo.manual.1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(OperatorCommandService::class);

    $result = $service->handleOutgoingMessage(
        phone: $lead->whatsapp,
        tenantId: $lead->tenant_id,
        agentId: $agent->id,
        instanceName: 'test-instance',
        message: 'Mensagem via interface',
        providerMessageId: 'evo.manual.1',
    );

    expect($result)->toBe(OperatorAction::Takeover);

    $count = DB::table('conversation_timeline_messages')
        ->where('lead_id', $lead->id)
        ->where('sender_type', 'human')
        ->where('body', 'Mensagem via interface')
        ->count();

    expect($count)->toBe(1);
});
