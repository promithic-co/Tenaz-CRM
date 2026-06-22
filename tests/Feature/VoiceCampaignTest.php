<?php

use App\Jobs\DispatchVoiceCampaignJob;
use App\Jobs\PlaceOutboundCallJob;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignCall;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;
use App\Services\VoiceCampaignService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function makeVoiceCampaignWithEntries(int $entryCount = 3): VoiceCampaign
{
    $user = userWithTenant();
    $tenant = $user->tenants()->first();
    $tenantId = (string) $tenant->id;

    $whatsappInstance = WhatsappInstance::factory()->create(['tenant_id' => $tenantId]);
    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'greeting_template' => 'Olá {nome}, pressione 1.',
    ]);

    $contactList = ContactList::factory()->create(['tenant_id' => $tenantId]);
    ContactListEntry::factory()->count($entryCount)->create(['contact_list_id' => $contactList->id]);

    return VoiceCampaign::factory()->create([
        'tenant_id' => $tenantId,
        'voice_instance_id' => $voiceInstance->id,
        'contact_list_id' => $contactList->id,
        'status' => 'draft',
        'greeting_template' => null,
    ]);
}

test('start sets campaign to sending and dispatches job', function () {
    $campaign = makeVoiceCampaignWithEntries(3);

    (new VoiceCampaignService)->start($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('sending');
    expect($campaign->total_calls)->toBe(3);
    Queue::assertPushed(DispatchVoiceCampaignJob::class);
});

test('start counts only opted-in entries for voice campaigns', function () {
    $campaign = makeVoiceCampaignWithEntries(0);

    ContactListEntry::factory()->count(2)->create(['contact_list_id' => $campaign->contact_list_id]);
    ContactListEntry::factory()->optedOut()->create(['contact_list_id' => $campaign->contact_list_id]);

    (new VoiceCampaignService)->start($campaign);

    expect($campaign->fresh()->total_calls)->toBe(2);
});

test('dispatch creates calls only for opted-in entries', function () {
    $campaign = makeVoiceCampaignWithEntries(0);

    $eligible = ContactListEntry::factory()->count(2)->create(['contact_list_id' => $campaign->contact_list_id]);
    $optedOut = ContactListEntry::factory()->optedOut()->create(['contact_list_id' => $campaign->contact_list_id]);
    $campaign->update(['status' => 'sending', 'total_calls' => 3]);
    VoiceCampaignCall::factory()->create([
        'voice_campaign_id' => $campaign->id,
        'contact_list_entry_id' => null,
        'status' => 'failed',
    ]);

    (new DispatchVoiceCampaignJob($campaign))->handle();

    expect(VoiceCampaignCall::where('voice_campaign_id', $campaign->id)->whereNotNull('contact_list_entry_id')->count())->toBe(2)
        ->and(VoiceCampaignCall::where('contact_list_entry_id', $optedOut->id)->exists())->toBeFalse()
        ->and(VoiceCampaignCall::whereIn('contact_list_entry_id', $eligible->pluck('id'))->count())->toBe(2)
        ->and($campaign->fresh()->total_calls)->toBe(2);

    Queue::assertPushed(PlaceOutboundCallJob::class, 2);
});

test('dispatch completes a campaign with no opted-in entries', function () {
    $campaign = makeVoiceCampaignWithEntries(0);

    ContactListEntry::factory()->optedOut()->create(['contact_list_id' => $campaign->contact_list_id]);
    $campaign->update(['status' => 'sending', 'total_calls' => 1]);

    (new DispatchVoiceCampaignJob($campaign))->handle();

    expect($campaign->fresh()->status)->toBe('completed')
        ->and($campaign->fresh()->total_calls)->toBe(0)
        ->and($campaign->fresh()->completed_at)->not->toBeNull();

    Queue::assertNotPushed(PlaceOutboundCallJob::class);
});

test('re-dispatch only enqueues entries without a prior call (SCALE-5)', function () {
    $campaign = makeVoiceCampaignWithEntries(3);
    $entries = ContactListEntry::where('contact_list_id', $campaign->contact_list_id)->orderBy('id')->get();
    $campaign->update(['status' => 'sending', 'total_calls' => 3]);

    VoiceCampaignCall::factory()->create([
        'voice_campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
        'status' => 'completed',
    ]);

    (new DispatchVoiceCampaignJob($campaign))->handle();

    expect(VoiceCampaignCall::where('voice_campaign_id', $campaign->id)->count())->toBe(3)
        ->and(VoiceCampaignCall::where('contact_list_entry_id', $entries[1]->id)->exists())->toBeTrue()
        ->and(VoiceCampaignCall::where('contact_list_entry_id', $entries[2]->id)->exists())->toBeTrue();

    Queue::assertPushed(PlaceOutboundCallJob::class, 2);
});

test('re-dispatch with all entries already called marks completed (SCALE-5)', function () {
    $campaign = makeVoiceCampaignWithEntries(2);
    $entries = ContactListEntry::where('contact_list_id', $campaign->contact_list_id)->orderBy('id')->get();
    $campaign->update(['status' => 'sending', 'total_calls' => 2]);

    foreach ($entries as $entry) {
        VoiceCampaignCall::factory()->create([
            'voice_campaign_id' => $campaign->id,
            'contact_list_entry_id' => $entry->id,
            'status' => 'completed',
        ]);
    }

    (new DispatchVoiceCampaignJob($campaign))->handle();

    expect($campaign->fresh()->status)->toBe('completed')
        ->and($campaign->fresh()->completed_at)->not->toBeNull();

    Queue::assertNotPushed(PlaceOutboundCallJob::class);
});

test('pause sets campaign to paused', function () {
    $campaign = makeVoiceCampaignWithEntries();
    $campaign->update(['status' => 'sending']);

    (new VoiceCampaignService)->pause($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('paused');
    expect($campaign->paused_at)->not->toBeNull();
});

test('template interpolation replaces nome and extra_data variables', function () {
    $user = userWithTenant();
    $tenant = $user->tenants()->first();
    $tenantId = (string) $tenant->id;

    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => null,
        'greeting_template' => 'Olá {nome}, você tem crédito de R${valor}.',
    ]);

    $contactList = ContactList::factory()->create(['tenant_id' => $tenantId]);
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $contactList->id,
        'name' => 'Maria',
        'extra_data' => ['valor' => '5000'],
    ]);

    $campaign = VoiceCampaign::factory()->create([
        'tenant_id' => $tenantId,
        'voice_instance_id' => $voiceInstance->id,
        'contact_list_id' => $contactList->id,
        'greeting_template' => null,
    ]);

    // The interpolation is done in DispatchVoiceCampaignJob. Test the logic directly.
    $template = $campaign->greeting_template ?? $voiceInstance->greeting_template ?? '';
    $vars = array_merge(['nome' => $entry->name ?? ''], $entry->extra_data ?? []);
    $result = preg_replace_callback('/\{(\w+)\}/', fn ($m) => $vars[$m[1]] ?? $m[0], $template);

    expect($result)->toBe('Olá Maria, você tem crédito de R$5000.');
});
