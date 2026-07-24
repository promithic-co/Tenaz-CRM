<?php

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Services\CampaignReplyDetector;
use App\Services\IncomingConversationPersister;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\mock;

/**
 * The backfill used to run only for leads created by the inbound itself, which is exactly
 * backwards for the case it matters most: a re-engagement campaign targets contacts who are
 * already leads, so the operator saw the reply with no visible trigger above it.
 */
function inboundArgs(string $tenantId, string $phone, array $overrides = []): array
{
    return array_merge([
        'tenantId' => $tenantId,
        'agentId' => null,
        'phone' => $phone,
        'name' => 'Backfill Tester',
        'instanceName' => '',
        'aggregatedMessage' => 'quero simular',
        'mediaContext' => null,
        'interactionId' => 'int-backfill-1',
        'providerMessageId' => 'wamid.BACKFILL1',
    ], $overrides);
}

/**
 * A campaign that already delivered a template to $phone, plus the lead that phone belongs to.
 *
 * @return array{tenantId: string, lead: Lead}
 */
function campaignSentTo(string $phone, string $providerMessageId): array
{
    $campaign = Campaign::factory()->sending()->create();
    $tenantId = (string) $campaign->tenant_id;

    $campaign->whatsappTemplate->update([
        'components_json' => [
            [
                'type' => 'BODY',
                'text' => '{{1}}, você tem margem disponível. Quer simular?',
                'example' => ['body_text' => [['Cliente']]],
            ],
        ],
    ]);

    $list = ContactList::factory()->create(['tenant_id' => $tenantId]);
    $entry = ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => $phone]);

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'delivered',
        'provider_message_id' => $providerMessageId,
        'sent_at' => now()->subHours(4),
        'template_params_resolved' => ['1' => 'Marcos'],
    ]);

    return [
        'tenantId' => $tenantId,
        'lead' => Lead::factory()->forTenant($tenantId)->create(['whatsapp' => $phone]),
    ];
}

test('an existing lead replying gets the campaign template that preceded the reply', function () {
    mock(CampaignReplyDetector::class)->shouldReceive('detect')->once()->andReturnNull();

    $phone = '5511964442201';
    ['tenantId' => $tenantId, 'lead' => $lead] = campaignSentTo($phone, 'wamid-precede-001');

    app(IncomingConversationPersister::class)->persist(...inboundArgs($tenantId, $phone));

    $templateRow = ConversationTimelineMessage::where('lead_id', $lead->id)
        ->where('source', 'campaign')
        ->first();

    expect($templateRow)->not->toBeNull()
        ->and($templateRow->provider_message_id)->toBe('wamid-precede-001')
        ->and($templateRow->body)->toBe('Marcos, você tem margem disponível. Quer simular?');
});

test('the backfilled template sorts ahead of the reply that triggered it', function () {
    mock(CampaignReplyDetector::class)->shouldReceive('detect')->once()->andReturnNull();

    $phone = '5511964442202';
    ['tenantId' => $tenantId, 'lead' => $lead] = campaignSentTo($phone, 'wamid-precede-002');

    app(IncomingConversationPersister::class)->persist(...inboundArgs($tenantId, $phone));

    $ordered = ConversationTimelineMessage::where('lead_id', $lead->id)
        ->orderBy('created_at')
        ->orderBy('id')
        ->pluck('direction')
        ->all();

    expect($ordered)->toBe(['outbound', 'inbound']);
});

test('a busy conversation does not re-scan campaign messages on every inbound', function () {
    mock(CampaignReplyDetector::class)->shouldReceive('detect')->twice()->andReturnNull();

    $phone = '5511964442203';
    ['tenantId' => $tenantId, 'lead' => $lead] = campaignSentTo($phone, 'wamid-precede-003');

    $persister = app(IncomingConversationPersister::class);
    $persister->persist(...inboundArgs($tenantId, $phone));
    $persister->persist(...inboundArgs($tenantId, $phone, ['providerMessageId' => 'wamid.BACKFILL2']));

    expect(Cache::has("campaign_backfill:{$lead->id}"))->toBeTrue()
        ->and(ConversationTimelineMessage::where('lead_id', $lead->id)->where('source', 'campaign')->count())
        ->toBe(1);
});

test('the reply is backfilled even when the lead carries the BR 9th digit and the campaign entry does not', function () {
    mock(CampaignReplyDetector::class)->shouldReceive('detect')->once()->andReturnNull();

    // Campaign list stores the 9-less form; the lead row (and the inbound) carry the 9.
    ['tenantId' => $tenantId] = campaignSentTo('556798601348', 'wamid-precede-9th');
    $lead = Lead::factory()->forTenant($tenantId)->create(['whatsapp' => '5567998601348']);

    app(IncomingConversationPersister::class)->persist(...inboundArgs($tenantId, '5567998601348'));

    expect(
        ConversationTimelineMessage::where('lead_id', $lead->id)->where('source', 'campaign')->count()
    )->toBe(1);
});
