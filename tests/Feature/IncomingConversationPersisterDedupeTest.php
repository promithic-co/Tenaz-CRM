<?php

use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Services\CampaignReplyDetector;
use App\Services\ConversationAutomationService;
use App\Services\ConversationTimelineService;
use App\Services\IncomingConversationPersister;
use Illuminate\Database\QueryException;

/**
 * @param  array<string, mixed>  $overrides
 */
function inboundTimelineRow(int $leadId, string $tenantId, array $overrides = []): array
{
    return array_merge([
        'tenant_id' => $tenantId,
        'lead_id' => $leadId,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'channel' => 'whatsapp',
        'status' => 'received',
        'source' => 'webhook',
        'provider_message_id' => 'wamid.IDX1',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dedupePersistArgs(array $overrides = []): array
{
    return array_merge([
        'tenantId' => 'tenant-dup',
        'agentId' => null,
        'phone' => '5511988887777',
        'name' => 'Dup Tester',
        'instanceName' => '',
        'aggregatedMessage' => 'oi',
        'mediaContext' => null,
        'interactionId' => 'int-dup-1',
        'providerMessageId' => 'wamid.DUP123',
    ], $overrides);
}

test('same provider_message_id persisted twice yields one timeline row and detects once', function () {
    $detector = $this->mock(CampaignReplyDetector::class);
    $detector->shouldReceive('detect')->once();

    $persister = app(IncomingConversationPersister::class);

    $first = $persister->persist(...dedupePersistArgs());
    $second = $persister->persist(...dedupePersistArgs(['interactionId' => 'int-dup-2']));

    expect($first['duplicate'])->toBeFalse();
    expect($second['duplicate'])->toBeTrue();
    expect($second['timelineMessage']->id)->toBe($first['timelineMessage']->id);
    expect(ConversationTimelineMessage::where('provider_message_id', 'wamid.DUP123')->count())->toBe(1);
});

test('inbound without provider id still persists (no lock, no dedupe)', function () {
    $this->mock(CampaignReplyDetector::class)->shouldReceive('detect')->twice();

    $persister = app(IncomingConversationPersister::class);

    $first = $persister->persist(...dedupePersistArgs(['providerMessageId' => null, 'interactionId' => 'int-np-1']));
    $second = $persister->persist(...dedupePersistArgs(['providerMessageId' => null, 'interactionId' => 'int-np-2']));

    expect($first['duplicate'])->toBeFalse();
    expect($second['duplicate'])->toBeFalse();
    expect(ConversationTimelineMessage::where('tenant_id', 'tenant-dup')->count())->toBe(2);
});

test('partial unique index rejects a second inbound row with the same provider id (ATOM-2)', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-idx', 'agent_id' => null]);

    ConversationTimelineMessage::create(inboundTimelineRow($lead->id, 'tenant-idx'));

    expect(fn () => ConversationTimelineMessage::create(inboundTimelineRow($lead->id, 'tenant-idx')))
        ->toThrow(QueryException::class);

    expect(ConversationTimelineMessage::where('provider_message_id', 'wamid.IDX1')->count())->toBe(1);
});

test('partial unique index ignores outbound rows and null provider ids (ATOM-2)', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-idx2', 'agent_id' => null]);

    // Same provider id is allowed across directions — the index is scoped to inbound only.
    ConversationTimelineMessage::create(inboundTimelineRow($lead->id, 'tenant-idx2', ['provider_message_id' => 'wamid.SHARED']));
    ConversationTimelineMessage::create(inboundTimelineRow($lead->id, 'tenant-idx2', ['direction' => 'outbound', 'sender_type' => 'agent', 'provider_message_id' => 'wamid.SHARED']));

    // Inbound rows with no provider id never collide — the predicate excludes NULLs.
    ConversationTimelineMessage::create(inboundTimelineRow($lead->id, 'tenant-idx2', ['provider_message_id' => null]));
    ConversationTimelineMessage::create(inboundTimelineRow($lead->id, 'tenant-idx2', ['provider_message_id' => null]));

    expect(ConversationTimelineMessage::where('tenant_id', 'tenant-idx2')->count())->toBe(4);
});

test('rolls back the followup flip when markInbound fails, persisting no partial inbound (ATOM-4)', function () {
    $this->mock(CampaignReplyDetector::class)->shouldReceive('detect')->zeroOrMoreTimes();

    $automation = $this->mock(ConversationAutomationService::class);
    $automation->shouldReceive('resolveMode')->andReturn('manual');
    $automation->shouldReceive('markInbound')->once()->andThrow(new RuntimeException('boom'));

    $lead = Lead::factory()->create([
        'tenant_id' => 'tenant-atom4',
        'whatsapp' => '5511955554444',
        'agent_id' => null,
        'followup_status' => 'active',
    ]);

    $persister = app(IncomingConversationPersister::class);

    expect(fn () => $persister->persist(...dedupePersistArgs([
        'tenantId' => 'tenant-atom4',
        'phone' => '5511955554444',
        'providerMessageId' => 'wamid.ATOM4',
        'interactionId' => 'int-atom4',
    ])))->toThrow(RuntimeException::class);

    // markInbound throwing rolls back the followup flip in the same transaction, and the
    // exception aborts persistGuarded before timeline->record — so the lead keeps its active
    // followups AND no message row is committed. No half-applied "dark" inbound survives.
    $lead->refresh();
    expect($lead->followup_status)->toBe('active')
        ->and(ConversationTimelineMessage::where('provider_message_id', 'wamid.ATOM4')->count())->toBe(0);
});

test('insert race that trips the unique index resolves to the existing row as a duplicate (ATOM-2)', function () {
    $this->mock(CampaignReplyDetector::class)->shouldReceive('detect')->once();

    $seedLead = Lead::factory()->create(['tenant_id' => 'tenant-dup', 'agent_id' => null]);
    $existingId = null;

    // Simulate the lock degrading: our SELECT-before-insert misses, the concurrent winner
    // inserts the row, then our own insert trips the partial unique index. The persister
    // must catch the violation and resolve to the winner's row rather than failing.
    $this->mock(ConversationTimelineService::class, function ($mock) use ($seedLead, &$existingId): void {
        $mock->shouldReceive('record')->once()->andReturnUsing(function () use ($seedLead, &$existingId) {
            $row = ConversationTimelineMessage::create(inboundTimelineRow($seedLead->id, 'tenant-dup', ['provider_message_id' => 'wamid.DUP123']));
            $existingId = $row->id;

            throw new QueryException('sqlite', 'insert', [], new RuntimeException('UNIQUE constraint failed', 23000));
        });
    });

    $result = app(IncomingConversationPersister::class)->persist(...dedupePersistArgs());

    expect($result['duplicate'])->toBeTrue()
        ->and($result['timelineMessage']->id)->toBe($existingId);
    expect(ConversationTimelineMessage::where('provider_message_id', 'wamid.DUP123')->count())->toBe(1);
});
