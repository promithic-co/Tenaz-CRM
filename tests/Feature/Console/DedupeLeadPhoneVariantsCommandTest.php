<?php

use App\Models\ConversationSession;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\ServiceTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const DEDUPE_WITH_NINTH = '5511987654321';

const DEDUPE_WITHOUT_NINTH = '551187654321';

/**
 * @return array{0: Lead, 1: Lead}
 */
function splitPair(string $tenantId = 'tenant-dedupe', array $withOverrides = [], array $withoutOverrides = []): array
{
    $withNinth = Lead::factory()->create(array_merge([
        'tenant_id' => $tenantId,
        'whatsapp' => DEDUPE_WITH_NINTH,
    ], $withOverrides));

    $withoutNinth = Lead::factory()->create(array_merge([
        'tenant_id' => $tenantId,
        'whatsapp' => DEDUPE_WITHOUT_NINTH,
    ], $withoutOverrides));

    return [$withNinth, $withoutNinth];
}

function timelineRowFor(Lead $lead, string $body): ConversationTimelineMessage
{
    return ConversationTimelineMessage::create([
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'channel' => 'whatsapp',
        'body' => $body,
        'status' => 'received',
        'source' => 'webhook',
    ]);
}

test('the two halves of a split conversation become one', function () {
    [$withNinth, $withoutNinth] = splitPair();
    timelineRowFor($withNinth, 'primeira metade');
    timelineRowFor($withoutNinth, 'segunda metade');

    $this->artisan('leads:dedupe-phone-variants')->assertSuccessful();

    expect(Lead::withoutGlobalScopes()->whereNull('deleted_at')->count())->toBe(1);
    expect($withNinth->fresh()->deleted_at)->toBeNull();
    expect($withoutNinth->fresh()->deleted_at)->not->toBeNull();

    // Both messages hang off the survivor, in one thread.
    expect(ConversationTimelineMessage::where('lead_id', $withNinth->id)->pluck('body')->all())
        ->toEqualCanonicalizing(['primeira metade', 'segunda metade']);
});

test('the canonical spelling survives even when the older row is the malformed one', function () {
    // Reverse creation order: the 12-digit row is older, so "oldest wins" would pick it.
    $withoutNinth = Lead::factory()->create([
        'tenant_id' => 'tenant-dedupe',
        'whatsapp' => DEDUPE_WITHOUT_NINTH,
    ]);
    $withNinth = Lead::factory()->create([
        'tenant_id' => 'tenant-dedupe',
        'whatsapp' => DEDUPE_WITH_NINTH,
    ]);

    $this->artisan('leads:dedupe-phone-variants')->assertSuccessful();

    expect($withNinth->fresh()->deleted_at)->toBeNull()
        ->and($withoutNinth->fresh()->deleted_at)->not->toBeNull();
});

test('a dry run reports the merge and writes nothing', function () {
    [$withNinth, $withoutNinth] = splitPair();

    $this->artisan('leads:dedupe-phone-variants', ['--dry-run' => true])->assertSuccessful();

    expect(Lead::withoutGlobalScopes()->whereNull('deleted_at')->count())->toBe(2)
        ->and($withoutNinth->fresh()->deleted_at)->toBeNull()
        ->and($withNinth->fresh()->deleted_at)->toBeNull();
});

test('the survivor takes what it was missing and the later of the two clocks', function () {
    $early = now()->subDays(3);
    $late = now()->subHour();

    [$withNinth, $withoutNinth] = splitPair(
        withOverrides: [
            'nome' => null,
            'cpf' => null,
            'last_interaction_at' => $early,
            'followup_count' => 1,
        ],
        withoutOverrides: [
            'nome' => 'Nome Que Faltava',
            'cpf' => '12345678901',
            'last_interaction_at' => $late,
            'followup_count' => 4,
        ],
    );

    $this->artisan('leads:dedupe-phone-variants')->assertSuccessful();

    $survivor = $withNinth->fresh();

    expect($survivor->nome)->toBe('Nome Que Faltava')
        ->and($survivor->cpf)->toBe('12345678901')
        ->and($survivor->followup_count)->toBe(4)
        ->and($survivor->last_interaction_at->timestamp)->toBe($late->timestamp);

    expect($withoutNinth->fresh()->deleted_at)->not->toBeNull();
});

test('a pause on the losing row binds the merged conversation', function () {
    $pausedUntil = now()->addHours(4);

    [$withNinth] = splitPair(
        withOverrides: ['ai_paused_until' => null],
        withoutOverrides: ['ai_paused_until' => $pausedUntil],
    );

    $this->artisan('leads:dedupe-phone-variants')->assertSuccessful();

    expect($withNinth->fresh()->ai_paused_until->timestamp)->toBe($pausedUntil->timestamp);
});

test('only one atendimento stays open and they are renumbered in order', function () {
    [$withNinth, $withoutNinth] = splitPair();

    $older = ConversationSession::create([
        'tenant_id' => 'tenant-dedupe',
        'lead_id' => $withNinth->id,
        'number' => 1,
        'status' => ConversationSession::STATUS_OPEN,
        'open_reason' => ConversationSession::OPEN_REASON_FIRST_CONTACT,
        'opened_at' => now()->subDays(5),
        'last_message_at' => now()->subDays(5),
    ]);

    $newer = ConversationSession::create([
        'tenant_id' => 'tenant-dedupe',
        'lead_id' => $withoutNinth->id,
        'number' => 1,
        'status' => ConversationSession::STATUS_OPEN,
        'open_reason' => ConversationSession::OPEN_REASON_FIRST_CONTACT,
        'opened_at' => now()->subDay(),
        'last_message_at' => now()->subHour(),
    ]);

    $this->artisan('leads:dedupe-phone-variants')->assertSuccessful();

    // The partial unique index allows exactly one open session per lead, so the merge has
    // to close one before the rows move — the one that saw the last message survives open.
    expect($newer->fresh()->status)->toBe(ConversationSession::STATUS_OPEN)
        ->and($older->fresh()->status)->toBe(ConversationSession::STATUS_CLOSED)
        ->and($older->fresh()->outcome)->toBe(ConversationSession::OUTCOME_ABANDONED);

    // Both were "Atendimento #1" before; chronological order breaks the tie.
    expect($older->fresh()->number)->toBe(1)
        ->and($newer->fresh()->number)->toBe(2)
        ->and($newer->fresh()->lead_id)->toBe($withNinth->id);
});

test('everything pointing at the loser follows it to the survivor', function () {
    [$withNinth, $withoutNinth] = splitPair();

    $ticket = ServiceTicket::create([
        'tenant_id' => 'tenant-dedupe',
        'lead_id' => $withoutNinth->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    DB::table('whatsapp_outbox_messages')->insert([
        'tenant_id' => 'tenant-dedupe',
        'lead_id' => $withoutNinth->id,
        'provider' => 'meta_cloud',
        'status' => 'pending',
        'payload_json' => json_encode(['type' => 'text']),
        'idempotency_key' => 'dedupe-test-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('leads:dedupe-phone-variants')->assertSuccessful();

    expect($ticket->fresh()->lead_id)->toBe($withNinth->id);
    expect(DB::table('whatsapp_outbox_messages')->where('idempotency_key', 'dedupe-test-1')->value('lead_id'))
        ->toBe($withNinth->id);
});

test('a tenant filter leaves the other tenants alone', function () {
    splitPair('tenant-a');
    [$otherWithNinth, $otherWithoutNinth] = splitPair('tenant-b');

    $this->artisan('leads:dedupe-phone-variants', ['--tenant' => 'tenant-a'])->assertSuccessful();

    expect($otherWithNinth->fresh()->deleted_at)->toBeNull()
        ->and($otherWithoutNinth->fresh()->deleted_at)->toBeNull();
    expect(Lead::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', 'tenant-a')->count())->toBe(1);
});

test('a landline and a mobile that merely look alike are left apart', function () {
    Lead::factory()->create(['tenant_id' => 'tenant-dedupe', 'whatsapp' => '551133334444']);
    Lead::factory()->create(['tenant_id' => 'tenant-dedupe', 'whatsapp' => '5511933334444']);

    $this->artisan('leads:dedupe-phone-variants')
        ->expectsOutputToContain('No duplicated leads found.')
        ->assertSuccessful();

    expect(Lead::withoutGlobalScopes()->whereNull('deleted_at')->count())->toBe(2);
});

test('a second run finds nothing left to merge', function () {
    splitPair();

    $this->artisan('leads:dedupe-phone-variants')->assertSuccessful();
    $this->artisan('leads:dedupe-phone-variants')
        ->expectsOutputToContain('No duplicated leads found.')
        ->assertSuccessful();
});
