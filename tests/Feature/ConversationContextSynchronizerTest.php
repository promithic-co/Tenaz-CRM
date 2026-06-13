<?php

use App\Models\Agent;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\User;
use App\Services\ConversationContextSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->sync = app(ConversationContextSynchronizer::class);
});

function makeSyncLead(?string $conversationId = null): Lead
{
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'name' => 'test-agent']);

    return Lead::factory()->create([
        'tenant_id' => (string) $user->id,
        'agent_id' => $agent->id,
        'conversation_id' => $conversationId,
        'whatsapp' => '5511900000000',
    ]);
}

function seedAgentConversation(string $conversationId): void
{
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'Test conversation',
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);
}

test('returns 0 when lead has no conversation_id', function () {
    $lead = makeSyncLead(conversationId: null);

    expect($this->sync->syncPending($lead))->toBe(0);
});

test('mirrors un-synced lead and operator rows into agent memory', function () {
    $convId = (string) Str::uuid();
    seedAgentConversation($convId);
    $lead = makeSyncLead($convId);

    // Three pending rows: lead inbound, operator outbound, lead inbound again
    ConversationTimelineMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'channel' => 'whatsapp',
        'body' => 'Quero falar com humano',
        'status' => 'received',
        'source' => 'webhook',
        'synced_to_agent_at' => null,
    ]);

    ConversationTimelineMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $convId,
        'direction' => 'outbound',
        'sender_type' => 'human',
        'channel' => 'whatsapp',
        'body' => 'Oi, eu sou o João',
        'status' => 'sent',
        'source' => 'manual',
        'synced_to_agent_at' => null,
    ]);

    ConversationTimelineMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'channel' => 'whatsapp',
        'body' => 'Aceito a proposta',
        'status' => 'received',
        'source' => 'webhook',
        'synced_to_agent_at' => null,
    ]);

    expect($this->sync->syncPending($lead))->toBe(3);

    $rows = DB::table('agent_conversation_messages')
        ->where('conversation_id', $convId)
        ->orderBy('created_at')
        ->get();

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->role)->toBe('user')
        ->and($rows[0]->content)->toBe('Quero falar com humano')
        ->and($rows[1]->role)->toBe('assistant')
        ->and($rows[1]->content)->toBe('Oi, eu sou o João')
        ->and(json_decode($rows[1]->attachments, true)['_aria_origin'])->toBe('operator')
        ->and($rows[2]->role)->toBe('user');

    // Timeline rows must be flipped to synced
    expect(ConversationTimelineMessage::whereNull('synced_to_agent_at')->where('lead_id', $lead->id)->count())->toBe(0);
});

test('subsequent calls are no-ops', function () {
    $convId = (string) Str::uuid();
    seedAgentConversation($convId);
    $lead = makeSyncLead($convId);

    ConversationTimelineMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'channel' => 'whatsapp',
        'body' => 'Olá',
        'status' => 'received',
        'source' => 'webhook',
        'synced_to_agent_at' => null,
    ]);

    expect($this->sync->syncPending($lead))->toBe(1)
        ->and($this->sync->syncPending($lead))->toBe(0);

    $rows = DB::table('agent_conversation_messages')
        ->where('conversation_id', $convId)->count();
    expect($rows)->toBe(1);
});

test('ignores agent sender_type rows because laravel/ai already wrote them', function () {
    $convId = (string) Str::uuid();
    seedAgentConversation($convId);
    $lead = makeSyncLead($convId);

    ConversationTimelineMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $convId,
        'direction' => 'outbound',
        'sender_type' => 'agent',
        'channel' => 'whatsapp',
        'body' => 'Resposta da IA',
        'status' => 'sent',
        'source' => 'agent',
        'synced_to_agent_at' => null, // intentionally null to verify filter
    ]);

    expect($this->sync->syncPending($lead))->toBe(0)
        ->and(DB::table('agent_conversation_messages')->where('conversation_id', $convId)->count())->toBe(0);
});

test('mirrors media metadata into attachments', function () {
    $convId = (string) Str::uuid();
    seedAgentConversation($convId);
    $lead = makeSyncLead($convId);

    ConversationTimelineMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'channel' => 'whatsapp',
        'body' => '',
        'media' => [
            'type' => 'audio',
            'mime_type' => 'audio/ogg',
            'duration_secs' => 12,
            'original_hash' => 'abc123',
        ],
        'status' => 'received',
        'source' => 'webhook',
        'synced_to_agent_at' => null,
    ]);

    $this->sync->syncPending($lead);

    $row = DB::table('agent_conversation_messages')
        ->where('conversation_id', $convId)->first();

    $attachments = json_decode($row->attachments, true);
    expect($attachments['_aria_media']['type'])->toBe('audio')
        ->and($attachments['_aria_media']['duration_secs'])->toBe(12)
        ->and($attachments['_aria_origin'])->toBe('lead');
});

test('records timeline source metadata into agent meta column', function () {
    $convId = (string) Str::uuid();
    seedAgentConversation($convId);
    $lead = makeSyncLead($convId);

    $timeline = ConversationTimelineMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $convId,
        'direction' => 'outbound',
        'sender_type' => 'human',
        'channel' => 'whatsapp',
        'body' => 'Tudo bem?',
        'status' => 'sent',
        'source' => 'manual',
        'synced_to_agent_at' => null,
    ]);

    $this->sync->syncPending($lead);

    $row = DB::table('agent_conversation_messages')
        ->where('conversation_id', $convId)->first();

    $meta = json_decode($row->meta, true);
    expect($meta['_aria_sync_origin'])->toBe('timeline')
        ->and($meta['_aria_timeline_id'])->toBe($timeline->id)
        ->and($meta['_aria_sender_type'])->toBe('human')
        ->and($meta['_aria_source'])->toBe('manual');
});
