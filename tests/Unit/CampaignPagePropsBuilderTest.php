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
use Inertia\DeferProp;

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
        'meta_template_name' => 'template_aprovado',
        'meta_waba_id' => $instance->meta_waba_id,
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

test('create props defer filters_json and template bodies out of the initial payload (FE-02)', function () {
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $user->tenantId,
        'user_id' => $user->id,
    ]);
    $filters = ['version' => 1, 'match' => 'all', 'rules' => [['field' => 'status', 'op' => 'eq', 'value' => 'new']]];
    $dynamicList = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => $filters,
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'template_deferred',
        'meta_waba_id' => $instance->meta_waba_id,
        'body' => 'Olá {{1}}, tudo bem?',
    ]);

    $props = app(CampaignPagePropsBuilder::class)->create(Request::create('/campanhas/create', 'GET'));

    $listAttributes = $props['contactLists']->firstWhere('id', $dynamicList->id)->getAttributes();
    $templateAttributes = $props['templates']->firstWhere('id', $template->id)->getAttributes();

    // The heavy fields are no longer shipped on the eager option lists.
    expect($listAttributes)->not->toHaveKey('filters_json')
        ->and($templateAttributes)->not->toHaveKey('body');

    // They are deferred and only materialise when the frontend resolves them.
    expect($props['contactListFilters'])->toBeInstanceOf(DeferProp::class)
        ->and($props['templateBodies'])->toBeInstanceOf(DeferProp::class);

    $resolvedFilters = ($props['contactListFilters'])();
    $resolvedBodies = ($props['templateBodies'])();

    expect($resolvedFilters[$dynamicList->id])->toBe($filters)
        ->and($resolvedBodies[$template->id])->toBe('Olá {{1}}, tudo bem?');
});

test('create props expose only complete templates owned by their instance WABA', function () {
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $user->tenantId,
        'user_id' => $user->id,
        'meta_waba_id' => 'waba-create-props',
    ]);
    $validTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'template_valido',
        'meta_waba_id' => $instance->meta_waba_id,
        'body' => 'Template válido',
    ]);
    $invalidTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'template_waba_incorreto',
        'meta_waba_id' => 'waba-foreign',
        'body' => 'Template inválido',
    ]);
    $unboundTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => null,
        'status' => 'APPROVED',
        'meta_template_name' => 'template_sem_instancia',
        'meta_waba_id' => $instance->meta_waba_id,
    ]);
    $incompleteTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'meta_template_name' => '   ',
        'meta_waba_id' => $instance->meta_waba_id,
    ]);

    $props = app(CampaignPagePropsBuilder::class)->create(Request::create('/campanhas/create', 'GET'));
    $templateIds = $props['templates']->pluck('id');
    $bodyIds = collect(($props['templateBodies'])())->keys();

    expect($templateIds)->toContain($validTemplate->id)
        ->and($templateIds)->not->toContain($invalidTemplate->id, $unboundTemplate->id, $incompleteTemplate->id)
        ->and($bodyIds)->toContain($validTemplate->id)
        ->and($bodyIds)->not->toContain($invalidTemplate->id, $unboundTemplate->id, $incompleteTemplate->id);
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
