<?php

use App\Models\ConversationTimelineMessage;
use App\Services\CampaignReplyDetector;
use App\Services\IncomingConversationPersister;

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
