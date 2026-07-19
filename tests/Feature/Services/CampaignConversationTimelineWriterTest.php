<?php

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Services\CampaignConversationTimelineWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function templateComponents(): array
{
    return [
        [
            'type' => 'BODY',
            'text' => 'Olá {{1}}, sua proposta {{2}} está pronta.',
            'example' => ['body_text' => [['Maria', '#000']]],
        ],
    ];
}

function writer(): CampaignConversationTimelineWriter
{
    return app(CampaignConversationTimelineWriter::class);
}

it('mirrors a sent campaign template into an existing lead conversation', function () {
    $campaign = Campaign::factory()->sending()->create();
    $entry = ContactListEntry::factory()->create();
    $lead = Lead::factory()->forTenant((string) $campaign->tenant_id)->create(['whatsapp' => $entry->phone]);

    writer()->mirrorSentTemplate(
        $campaign,
        $entry,
        (string) $entry->phone,
        'wamid-001',
        ['1' => 'João', '2' => '#42'],
        templateComponents(),
    );

    $row = ConversationTimelineMessage::where('lead_id', $lead->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->direction)->toBe('outbound')
        ->and($row->sender_type)->toBe('system')
        ->and($row->source)->toBe('campaign')
        ->and($row->status)->toBe('sent')
        ->and($row->provider_message_id)->toBe('wamid-001')
        ->and($row->body)->toBe('Olá João, sua proposta #42 está pronta.');
});

it('does not mirror or create a lead when the recipient has no conversation', function () {
    $campaign = Campaign::factory()->sending()->create();
    $entry = ContactListEntry::factory()->create();

    writer()->mirrorSentTemplate(
        $campaign,
        $entry,
        (string) $entry->phone,
        'wamid-002',
        ['1' => 'João', '2' => '#42'],
        templateComponents(),
    );

    expect(ConversationTimelineMessage::count())->toBe(0)
        ->and(Lead::where('whatsapp', $entry->phone)->count())->toBe(0);
});

it('does not duplicate the mirrored row on a repeated send event', function () {
    $campaign = Campaign::factory()->sending()->create();
    $entry = ContactListEntry::factory()->create();
    $lead = Lead::factory()->forTenant((string) $campaign->tenant_id)->create(['whatsapp' => $entry->phone]);

    foreach (range(1, 2) as $ignored) {
        writer()->mirrorSentTemplate(
            $campaign,
            $entry,
            (string) $entry->phone,
            'wamid-003',
            ['1' => 'João', '2' => '#42'],
            templateComponents(),
        );
    }

    expect(ConversationTimelineMessage::where('lead_id', $lead->id)->count())->toBe(1);
});

it('backfills recent campaign templates for a newly created lead at their original time', function () {
    $campaign = Campaign::factory()->sending()->create();
    $campaign->whatsappTemplate->update(['components_json' => templateComponents()]);
    $list = ContactList::factory()->create(['tenant_id' => $campaign->tenant_id]);
    $lead = Lead::factory()->forTenant((string) $campaign->tenant_id)->create();
    $entry = ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => $lead->whatsapp]);

    $sentAt = now()->subHours(3);
    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'read',
        'provider_message_id' => 'wamid-back-001',
        'sent_at' => $sentAt,
        'template_params_resolved' => ['1' => 'Ana', '2' => '#7'],
    ]);

    writer()->backfillForNewLead($lead->fresh());

    $row = ConversationTimelineMessage::where('lead_id', $lead->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->source)->toBe('campaign')
        ->and($row->status)->toBe('read')
        ->and($row->body)->toBe('Olá Ana, sua proposta #7 está pronta.')
        ->and($row->created_at->timestamp)->toBe($sentAt->timestamp);
});

it('backfill is idempotent on provider_message_id', function () {
    $campaign = Campaign::factory()->sending()->create();
    $campaign->whatsappTemplate->update(['components_json' => templateComponents()]);
    $list = ContactList::factory()->create(['tenant_id' => $campaign->tenant_id]);
    $lead = Lead::factory()->forTenant((string) $campaign->tenant_id)->create();
    $entry = ContactListEntry::factory()->create(['contact_list_id' => $list->id, 'phone' => $lead->whatsapp]);

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'sent',
        'provider_message_id' => 'wamid-back-002',
        'sent_at' => now()->subHour(),
        'template_params_resolved' => ['1' => 'Ana', '2' => '#7'],
    ]);

    writer()->backfillForNewLead($lead->fresh());
    writer()->backfillForNewLead($lead->fresh());

    expect(ConversationTimelineMessage::where('lead_id', $lead->id)->count())->toBe(1);
});
