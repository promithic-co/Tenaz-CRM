<?php

use App\Models\Lead;
use App\Services\ConversationTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ConversationTimelineService::class);
});

function seedLegacyConversation(string $conversationId): void
{
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'Legacy conversation',
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);
}

/**
 * @param  array<string, mixed>|null  $media
 */
function seedLegacyMessage(string $conversationId, string $role, string $content, \Carbon\CarbonInterface $at, ?array $media = null): void
{
    DB::table('agent_conversation_messages')->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => null,
        'agent' => 'test-agent',
        'role' => $role,
        'content' => $content,
        'attachments' => $media !== null ? json_encode(['_aria_media' => $media, '_aria_origin' => 'lead']) : '',
        'tool_calls' => '',
        'tool_results' => '',
        'usage' => '',
        'meta' => '',
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

test('returns empty array when lead has no conversation', function () {
    $lead = Lead::factory()->create(['conversation_id' => null]);

    expect($this->service->legacyMessages($lead))->toBe([]);
});

test('ascending read matches the backfill inline shape including media', function () {
    $conversationId = (string) \Illuminate\Support\Str::uuid();
    seedLegacyConversation($conversationId);

    seedLegacyMessage($conversationId, 'user', 'oi', now()->subMinutes(3));
    seedLegacyMessage($conversationId, 'assistant', 'audio reply', now()->subMinutes(2), [
        'type' => 'audio',
        'mime_type' => 'audio/ogg',
        'duration_secs' => 12,
    ]);

    $lead = Lead::factory()->create(['conversation_id' => $conversationId]);

    // Legacy backfill inline read (ConversasController:233-245).
    $legacyInline = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->orderBy('created_at')
        ->get(['role', 'content', 'attachments', 'created_at'])
        ->map(fn ($m) => [
            'role' => $m->role,
            'content' => $m->content,
            'hora' => \Carbon\Carbon::parse($m->created_at)->format('H:i'),
            'media' => $m->attachments
                ? (json_decode($m->attachments, true)['_aria_media'] ?? null)
                : null,
        ])
        ->toArray();

    expect($this->service->legacyMessages($lead))->toEqual($legacyInline);
});

test('newest-first read matches the preview inline shape (latest N re-sorted ascending)', function () {
    $conversationId = (string) \Illuminate\Support\Str::uuid();
    seedLegacyConversation($conversationId);

    foreach (range(1, 8) as $i) {
        seedLegacyMessage($conversationId, $i % 2 ? 'user' : 'assistant', "msg-{$i}", now()->subMinutes(10 - $i));
    }

    $lead = Lead::factory()->create(['conversation_id' => $conversationId]);

    // Legacy preview inline read (ConversasController:356-368), media-excluded.
    $previewInline = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->orderByDesc('created_at')
        ->limit(5)
        ->get(['role', 'content', 'created_at'])
        ->sortBy('created_at')
        ->values()
        ->map(fn ($m) => [
            'role' => $m->role,
            'content' => $m->content,
            'hora' => \Carbon\Carbon::parse($m->created_at)->format('H:i'),
        ])
        ->toArray();

    $actual = $this->service->legacyMessages($lead, limit: 5, newestFirst: true);

    // Service always carries a `media` key; preview omits it. Compare on the
    // shared keys to prove ordering + content + time parity.
    expect(array_map(fn ($m) => \Illuminate\Support\Arr::only($m, ['role', 'content', 'hora']), $actual))
        ->toBe($previewInline)
        ->and($actual)->toHaveCount(5)
        ->and($actual[0]['content'])->toBe('msg-4')
        ->and($actual[4]['content'])->toBe('msg-8');
});

test('ascending read matches the playground getMessages inline shape (media-excluded)', function () {
    $conversationId = (string) \Illuminate\Support\Str::uuid();
    seedLegacyConversation($conversationId);

    seedLegacyMessage($conversationId, 'user', 'q', now()->subMinutes(2));
    seedLegacyMessage($conversationId, 'assistant', 'a', now()->subMinute());

    $lead = Lead::factory()->create(['conversation_id' => $conversationId]);

    // Legacy getMessages inline read (PlaygroundController:535-544).
    $getMessagesInline = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->orderBy('created_at')
        ->get(['role', 'content', 'created_at'])
        ->map(fn ($m) => [
            'role' => $m->role,
            'content' => $m->content,
            'hora' => \Carbon\Carbon::parse($m->created_at)->format('H:i'),
        ])
        ->toArray();

    $actual = $this->service->legacyMessages($lead);

    expect(array_map(fn ($m) => \Illuminate\Support\Arr::only($m, ['role', 'content', 'hora']), $actual))
        ->toBe($getMessagesInline)
        ->and($actual[0]['media'])->toBeNull();
});
