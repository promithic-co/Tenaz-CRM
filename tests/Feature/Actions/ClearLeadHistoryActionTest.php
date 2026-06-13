<?php

use App\Actions\ClearLeadHistoryAction;
use App\Models\FollowupMessage;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = app(ClearLeadHistoryAction::class);
});

function seedConversationFor(Lead $lead): string
{
    $conversationId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'conv',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => null,
        'agent' => 'a',
        'role' => 'user',
        'content' => 'hi',
        'attachments' => '',
        'tool_calls' => '',
        'tool_results' => '',
        'usage' => '',
        'meta' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $lead->update(['conversation_id' => $conversationId]);

    return $conversationId;
}

test('clearForLead wipes conversation, messages, timeline and followups but keeps the lead', function () {
    $lead = Lead::factory()->create([
        'followup_count' => 3,
        'followup_status' => 'active',
        'service_window_expires_at' => null,
    ]);
    $conversationId = seedConversationFor($lead);

    DB::table('conversation_timeline_messages')->insert([
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $conversationId,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'channel' => 'whatsapp',
        'body' => 'hello',
        'status' => 'received',
        'source' => 'webhook',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    FollowupMessage::factory()->create(['lead_id' => $lead->id, 'tenant_id' => (string) $lead->tenant_id]);

    $this->action->clearForLead($lead->fresh());

    expect(Lead::find($lead->id))->not->toBeNull()
        ->and(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(0)
        ->and(DB::table('agent_conversations')->where('id', $conversationId)->count())->toBe(0)
        ->and(DB::table('conversation_timeline_messages')->where('lead_id', $lead->id)->count())->toBe(0)
        ->and(FollowupMessage::withoutGlobalScopes()->where('lead_id', $lead->id)->count())->toBe(0);

    $lead->refresh();
    expect($lead->conversation_id)->toBeNull()
        ->and($lead->followup_count)->toBe(0);
});

test('clearForLead recomputes followup_status active when still inside the window', function () {
    $lead = Lead::factory()->create([
        'followup_count' => 2,
        'followup_status' => 'inactive',
        'service_window_expires_at' => now()->addHours(5),
    ]);
    seedConversationFor($lead);

    $this->action->clearForLead($lead->fresh());

    expect($lead->fresh()->followup_status)->toBe('active');
});

test('clearForLead recomputes followup_status inactive when outside the window', function () {
    $lead = Lead::factory()->create([
        'followup_count' => 2,
        'followup_status' => 'active',
        'service_window_expires_at' => now()->subHour(),
        'last_inbound_at' => null,
        'free_entry_point_expires_at' => null,
    ]);
    seedConversationFor($lead);

    $this->action->clearForLead($lead->fresh());

    expect($lead->fresh()->followup_status)->toBe('inactive');
});

test('clearSandboxConversation deletes only conversation rows and leaves lead untouched', function () {
    $lead = Lead::factory()->sandbox()->create([
        'followup_count' => 1,
        'followup_status' => 'active',
    ]);
    $conversationId = seedConversationFor($lead);

    DB::table('conversation_timeline_messages')->insert([
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'conversation_id' => $conversationId,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'channel' => 'whatsapp',
        'body' => 'keep me',
        'status' => 'received',
        'source' => 'webhook',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->action->clearSandboxConversation($lead->fresh());

    expect(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(0)
        ->and(DB::table('agent_conversations')->where('id', $conversationId)->count())->toBe(0)
        // Timeline + lead attributes are intentionally left alone for the sandbox variant.
        ->and(DB::table('conversation_timeline_messages')->where('lead_id', $lead->id)->count())->toBe(1);

    $lead->refresh();
    expect(Lead::find($lead->id))->not->toBeNull()
        ->and($lead->conversation_id)->toBe($conversationId)
        ->and($lead->followup_count)->toBe(1)
        ->and($lead->followup_status)->toBe('active');
});

test('clearSandboxConversation is a no-op when the lead has no conversation', function () {
    $lead = Lead::factory()->sandbox()->create(['conversation_id' => null]);

    $this->action->clearSandboxConversation($lead);

    expect(Lead::find($lead->id))->not->toBeNull();
});
