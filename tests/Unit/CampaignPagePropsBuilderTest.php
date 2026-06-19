<?php

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\CampaignPagePropsBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('create props preserve campaign creation page contract', function () {
    $user = User::factory()->create();
    $contactList = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'name' => 'Lista Junho',
    ]);
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $user->tenantId,
        'user_id' => $user->id,
        'name' => 'meta-junho',
    ]);
    $approvedTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'name' => 'Template aprovado',
    ]);
    WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'REJECTED',
        'name' => 'Template rejeitado',
    ]);

    $request = Request::create('/campanhas/create', 'GET', [
        'contact_list_id' => $contactList->id,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $props = app(CampaignPagePropsBuilder::class)->create($request);

    expect($props)->toHaveKeys(['contactLists', 'templates', 'instances', 'defaults'])
        ->and($props['defaults'])->toBe([
            'contact_list_id' => $contactList->id,
            'whatsapp_instance_id' => $instance->id,
        ])
        ->and($props['contactLists']->pluck('id'))->toContain($contactList->id)
        ->and($props['instances']->pluck('id'))->toContain($instance->id)
        ->and($props['templates']->pluck('id'))->toContain($approvedTemplate->id)
        ->and($props['templates']->pluck('name'))->not->toContain('Template rejeitado');
});

test('show props preserve campaign detail contract and status filter', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'name' => 'Campanha Detail',
    ]);
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'name' => 'Cliente Um',
    ]);
    $failedEntry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'name' => 'Cliente Dois',
    ]);

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'sent',
        'sent_at' => now(),
    ]);
    CampaignMessage::factory()->failed()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $failedEntry->id,
    ]);
    Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'campaign_id' => $campaign->id,
    ]);

    $request = Request::create('/campanhas/'.$campaign->id, 'GET', ['status' => 'failed']);

    $props = app(CampaignPagePropsBuilder::class)->show($campaign, $request);

    expect($props)->toHaveKeys(['campaign', 'messages', 'repliedCount'])
        ->and($props['campaign']->relationLoaded('contactList'))->toBeTrue()
        ->and($props['campaign']->relationLoaded('whatsappTemplate'))->toBeTrue()
        ->and($props['campaign']->relationLoaded('whatsappInstance'))->toBeTrue()
        ->and($props['messages']->total())->toBe(1)
        ->and($props['messages']->items()[0]->status)->toBe('failed')
        ->and($props['repliedCount'])->toBe(1);
});
