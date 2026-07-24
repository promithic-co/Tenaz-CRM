<?php

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;

/**
 * Seed a campaign that delivered a template $daysAgo, plus the lead it reached.
 *
 * @return array{tenantId: string, lead: Lead}
 */
function historicalCampaignSend(string $providerMessageId, int $daysAgo): array
{
    $campaign = Campaign::factory()->sending()->create();
    $tenantId = (string) $campaign->tenant_id;

    $campaign->whatsappTemplate->update([
        'components_json' => [
            [
                'type' => 'BODY',
                'text' => 'Olá {{1}}, seu benefício foi liberado.',
                'example' => ['body_text' => [['Cliente']]],
            ],
        ],
    ]);

    $lead = Lead::factory()->forTenant($tenantId)->create();
    $list = ContactList::factory()->create(['tenant_id' => $tenantId]);
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => $lead->whatsapp,
    ]);

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'delivered',
        'provider_message_id' => $providerMessageId,
        'sent_at' => now()->subDays($daysAgo),
        'template_params_resolved' => ['1' => 'Manoel'],
    ]);

    return ['tenantId' => $tenantId, 'lead' => $lead];
}

test('it replays a campaign template that predates the mirror', function () {
    ['lead' => $lead] = historicalCampaignSend('wamid-hist-001', daysAgo: 45);

    $this->artisan('timeline:backfill-campaign-templates')->assertSuccessful();

    $row = ConversationTimelineMessage::where('lead_id', $lead->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->source)->toBe('campaign')
        ->and($row->body)->toBe('Olá Manoel, seu benefício foi liberado.');
});

test('a dry run reports without writing', function () {
    ['lead' => $lead] = historicalCampaignSend('wamid-hist-002', daysAgo: 10);

    $this->artisan('timeline:backfill-campaign-templates --dry-run')->assertSuccessful();

    expect(ConversationTimelineMessage::where('lead_id', $lead->id)->count())->toBe(0);
});

test('re-running writes nothing new', function () {
    ['lead' => $lead] = historicalCampaignSend('wamid-hist-003', daysAgo: 10);

    $this->artisan('timeline:backfill-campaign-templates')->assertSuccessful();
    $this->artisan('timeline:backfill-campaign-templates')->assertSuccessful();

    expect(ConversationTimelineMessage::where('lead_id', $lead->id)->count())->toBe(1);
});

test('sends older than the requested window are left alone', function () {
    ['lead' => $lead] = historicalCampaignSend('wamid-hist-004', daysAgo: 45);

    $this->artisan('timeline:backfill-campaign-templates --days=30')->assertSuccessful();

    expect(ConversationTimelineMessage::where('lead_id', $lead->id)->count())->toBe(0);
});

test('the tenant option confines the replay to one tenant', function () {
    ['lead' => $target, 'tenantId' => $tenantId] = historicalCampaignSend('wamid-hist-005', daysAgo: 5);
    ['lead' => $other] = historicalCampaignSend('wamid-hist-006', daysAgo: 5);

    $this->artisan("timeline:backfill-campaign-templates --tenant={$tenantId}")->assertSuccessful();

    expect(ConversationTimelineMessage::where('lead_id', $target->id)->count())->toBe(1)
        ->and(ConversationTimelineMessage::where('lead_id', $other->id)->count())->toBe(0);
});
