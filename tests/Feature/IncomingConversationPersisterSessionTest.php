<?php

use App\Models\Campaign;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Services\CampaignReplyDetector;
use App\Services\IncomingConversationPersister;

use function Pest\Laravel\mock;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function persistArgs(array $overrides = []): array
{
    return array_merge([
        'tenantId' => 'tenant-sess',
        'agentId' => null,
        'phone' => '5511977776666',
        'name' => 'Sess Tester',
        'instanceName' => '',
        'aggregatedMessage' => 'oi',
        'mediaContext' => null,
        'interactionId' => 'int-sess-1',
        'providerMessageId' => 'wamid.SESS1',
    ], $overrides);
}

function openSessionFor(Lead $lead): ?ConversationSession
{
    return ConversationSession::withoutGlobalScopes()
        ->where('lead_id', $lead->id)
        ->where('status', ConversationSession::STATUS_OPEN)
        ->first();
}

/**
 * A lightweight Campaign stand-in: the persister only reads $campaign->id for attribution,
 * so an unsaved model avoids the campaign factory's heavy FK chain (instance/template/list).
 */
function stubCampaign(int $id): Campaign
{
    return (new Campaign)->forceFill(['id' => $id, 'tenant_id' => 'tenant-sess']);
}

test('a first inbound opens a first_contact session', function () {
    mock(CampaignReplyDetector::class)->shouldReceive('detect')->once()->andReturnNull();

    $result = app(IncomingConversationPersister::class)->persist(...persistArgs());
    $session = openSessionFor($result['lead']);

    expect($session)->not->toBeNull()
        ->and($session->open_reason)->toBe(ConversationSession::OPEN_REASON_FIRST_CONTACT)
        ->and($result['timelineMessage']->session_id)->toBe($session->id);
});

test('a campaign reply opens a campaign-attributed session with campaign_id metadata', function () {
    $campaign = stubCampaign(4242);
    mock(CampaignReplyDetector::class)->shouldReceive('detect')->once()->andReturn($campaign);

    $result = app(IncomingConversationPersister::class)->persist(...persistArgs());
    $session = openSessionFor($result['lead']);

    expect($session->open_reason)->toBe(ConversationSession::OPEN_REASON_CAMPAIGN)
        ->and($session->metadata['campaign_id'])->toBe(4242);
});

test('a second inbound reuses the already open session', function () {
    mock(CampaignReplyDetector::class)->shouldReceive('detect')->twice()->andReturnNull();

    $persister = app(IncomingConversationPersister::class);
    $first = $persister->persist(...persistArgs());
    $second = $persister->persist(...persistArgs(['providerMessageId' => 'wamid.SESS2', 'interactionId' => 'int-sess-2']));

    expect($second['lead']->id)->toBe($first['lead']->id)
        ->and(ConversationSession::withoutGlobalScopes()->where('lead_id', $first['lead']->id)->count())->toBe(1);
});

test('an inbound after a closed session on a non-terminal lead reopens as reengagement_after_inactivity', function () {
    $lead = Lead::factory()->create([
        'tenant_id' => 'tenant-sess',
        'whatsapp' => '5511977776666',
        'status' => 'novo',
        'agent_id' => null,
    ]);
    ConversationSession::factory()->forLead($lead)->closed(ConversationSession::OUTCOME_NO_RESPONSE)->create(['number' => 1]);

    mock(CampaignReplyDetector::class)->shouldReceive('detect')->once()->andReturnNull();

    $result = app(IncomingConversationPersister::class)->persist(...persistArgs());
    $session = openSessionFor($result['lead']);

    expect($session->number)->toBe(2)
        ->and($session->open_reason)->toBe(ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_INACTIVITY);
});

test('a campaign reply after a terminal status keeps the post-terminal reason and guard, still stamping campaign_id', function () {
    $lead = Lead::factory()->create([
        'tenant_id' => 'tenant-sess',
        'whatsapp' => '5511977776666',
        'status' => 'convertido',
        'operational_stage' => Lead::STAGE_WON,
        'agent_id' => null,
    ]);
    ConversationSession::factory()->forLead($lead)->closed(ConversationSession::OUTCOME_CONVERTED)->create(['number' => 1]);

    $campaign = stubCampaign(5353);
    mock(CampaignReplyDetector::class)->shouldReceive('detect')->once()->andReturn($campaign);

    $result = app(IncomingConversationPersister::class)->persist(...persistArgs());
    $session = openSessionFor($result['lead']);

    // The safety guard wins the open_reason (AI must not answer a concluded sale) but the
    // campaign attribution is still recorded in metadata.
    expect($session->open_reason)->toBe(ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL)
        ->and($session->metadata['campaign_id'])->toBe(5353)
        ->and($result['lead']->fresh()->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);
});
