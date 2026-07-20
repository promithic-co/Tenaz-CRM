<?php

use App\Models\ConversationSession;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTimelineMessage(Lead $lead, string $body, ?CarbonInterface $at = null): ConversationTimelineMessage
{
    $message = ConversationTimelineMessage::create([
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'status' => 'received',
        'source' => 'webhook',
        'body' => $body,
    ]);

    if ($at !== null) {
        $message->forceFill(['created_at' => $at])->saveQuietly();
    }

    return $message;
}

test('backfill creates one open session per active lead and stamps its timeline', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-s', 'status' => 'novo']);
    $first = makeTimelineMessage($lead, 'oi', now()->subDays(3));
    $last = makeTimelineMessage($lead, 'tudo bem?', now()->subDay());

    $this->artisan('sessions:backfill')->assertSuccessful();

    $session = ConversationSession::withoutGlobalScopes()->where('lead_id', $lead->id)->sole();

    expect($session->status)->toBe(ConversationSession::STATUS_OPEN)
        ->and($session->outcome)->toBeNull()
        ->and($session->number)->toBe(1)
        ->and($session->open_reason)->toBe(ConversationSession::OPEN_REASON_FIRST_CONTACT);

    expect($first->fresh()->session_id)->toBe($session->id)
        ->and($last->fresh()->session_id)->toBe($session->id);
});

test('backfill closes session with inferred outcome for terminal leads', function () {
    $won = Lead::factory()->create(['tenant_id' => 'tenant-s', 'status' => 'convertido']);
    $lost = Lead::factory()->create(['tenant_id' => 'tenant-s', 'status' => 'desqualificado']);

    $this->artisan('sessions:backfill')->assertSuccessful();

    $wonSession = ConversationSession::withoutGlobalScopes()->where('lead_id', $won->id)->sole();
    $lostSession = ConversationSession::withoutGlobalScopes()->where('lead_id', $lost->id)->sole();

    expect($wonSession->status)->toBe(ConversationSession::STATUS_CLOSED)
        ->and($wonSession->outcome)->toBe(ConversationSession::OUTCOME_CONVERTED)
        ->and($wonSession->closed_at)->not->toBeNull();

    expect($lostSession->status)->toBe(ConversationSession::STATUS_CLOSED)
        ->and($lostSession->outcome)->toBe(ConversationSession::OUTCOME_LOST);
});

test('backfill is idempotent', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-s']);
    makeTimelineMessage($lead, 'oi');

    $this->artisan('sessions:backfill')->assertSuccessful();
    $count = ConversationSession::withoutGlobalScopes()->count();

    $this->artisan('sessions:backfill')->assertSuccessful();

    expect(ConversationSession::withoutGlobalScopes()->count())->toBe($count);
});

test('dry run writes nothing', function () {
    Lead::factory()->create(['tenant_id' => 'tenant-s']);

    $this->artisan('sessions:backfill --dry-run')->assertSuccessful();

    expect(ConversationSession::withoutGlobalScopes()->count())->toBe(0);
});

test('a lead can have at most one open session', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-s']);

    ConversationSession::factory()->forLead($lead)->open()->create();

    expect(fn () => ConversationSession::factory()->forLead($lead)->open()->create())
        ->toThrow(QueryException::class);
});

test('a lead can hold a closed session alongside a new open one', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-s']);

    ConversationSession::factory()->forLead($lead)->closed()->create(['number' => 1]);
    $open = ConversationSession::factory()->forLead($lead)->open()->create(['number' => 2]);

    expect($lead->openSession()->first()->id)->toBe($open->id)
        ->and($lead->sessions()->count())->toBe(2);
});
