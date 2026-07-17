<?php

use App\Enums\TemplateKind;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\DB;

function phase09CampaignInstance(User $user, ?string $wabaId = null): WhatsappInstance
{
    return WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'meta_waba_id' => $wabaId ?? fake()->unique()->numerify('###############'),
    ]);
}

/** @param array<string, mixed> $overrides */
function phase09CampaignTemplate(
    User $user,
    WhatsappInstance $instance,
    array $overrides = [],
): WhatsappTemplate {
    return WhatsappTemplate::factory()->create(array_merge([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'kind' => TemplateKind::MetaHsm->value,
        'status' => 'APPROVED',
        'meta_template_name' => 'campanha_integridade',
        'meta_waba_id' => $instance->meta_waba_id,
        'language' => 'pt_BR',
    ], $overrides));
}

/** @return array<string, mixed> */
function phase09StorePayload(
    WhatsappInstance $instance,
    ContactList $contactList,
    WhatsappTemplate $template,
): array {
    return [
        'name' => 'Campanha valida',
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $contactList->id,
        'whatsapp_template_id' => $template->id,
        'daily_limit' => 1000,
        'delay_between_ms' => 1000,
        'error_threshold_percent' => 10,
    ];
}

test('store accepts a complete template owned by the submitted instance and WABA', function () {
    $user = userWithTenant();
    $instance = phase09CampaignInstance($user, 'waba-valid');
    $template = phase09CampaignTemplate($user, $instance);
    $contactList = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    $response = $this->actingAs($user)->post('/campanhas', phase09StorePayload(
        $instance,
        $contactList,
        $template,
    ));

    $campaign = Campaign::query()->where('name', 'Campanha valida')->first();

    expect($campaign)->not->toBeNull()
        ->and($campaign->whatsapp_instance_id)->toBe($instance->id)
        ->and($campaign->whatsapp_template_id)->toBe($template->id);
    $response->assertRedirect(route('campanhas.show', $campaign));
});

test('store rejects templates with invalid campaign send identity', function (string $violation) {
    $user = userWithTenant();
    $instance = phase09CampaignInstance($user, 'waba-primary');
    $template = phase09CampaignTemplate($user, $instance);
    $contactList = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    match ($violation) {
        'instance' => $template->update([
            'whatsapp_instance_id' => phase09CampaignInstance($user, 'waba-secondary')->id,
            'meta_waba_id' => 'waba-secondary',
        ]),
        'waba' => $template->update(['meta_waba_id' => 'waba-foreign']),
        'instance_waba' => $instance->update(['meta_waba_id' => null]),
        'tenant' => $template->update(['tenant_id' => userWithTenant()->tenantId]),
        'status' => $template->update(['status' => 'PAUSED']),
        'kind' => DB::table('whatsapp_templates')->where('id', $template->id)->update(['kind' => 'legacy']),
        'meta_name' => $template->update(['meta_template_name' => '   ']),
        'language' => $template->update(['language' => '   ']),
        'template_waba' => $template->update(['meta_waba_id' => null]),
    };

    $response = $this->actingAs($user)->post('/campanhas', phase09StorePayload(
        $instance,
        $contactList,
        $template,
    ));

    $response->assertInvalid(['whatsapp_template_id']);
    expect(Campaign::query()->where('name', 'Campanha valida')->exists())->toBeFalse();
})->with([
    'different instance' => 'instance',
    'different WABA' => 'waba',
    'missing instance WABA' => 'instance_waba',
    'different tenant' => 'tenant',
    'not approved' => 'status',
    'not Meta HSM' => 'kind',
    'missing Meta name' => 'meta_name',
    'missing language' => 'language',
    'missing template WABA' => 'template_waba',
]);

test('update validates a selected template against the campaign immutable instance', function (string $violation) {
    $user = userWithTenant();
    $campaignInstance = phase09CampaignInstance($user, 'waba-campaign');
    $currentTemplate = phase09CampaignTemplate($user, $campaignInstance, [
        'meta_template_name' => 'template_atual',
    ]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $campaignInstance->id,
        'whatsapp_template_id' => $currentTemplate->id,
        'status' => 'draft',
    ]);

    $candidate = phase09CampaignTemplate($user, $campaignInstance, [
        'meta_template_name' => 'template_candidato',
    ]);

    match ($violation) {
        'instance' => $candidate->update([
            'whatsapp_instance_id' => phase09CampaignInstance($user, 'waba-other')->id,
            'meta_waba_id' => 'waba-other',
        ]),
        'waba' => $candidate->update(['meta_waba_id' => 'waba-other']),
        'instance_waba' => $campaignInstance->update(['meta_waba_id' => null]),
        'tenant' => $candidate->update(['tenant_id' => userWithTenant()->tenantId]),
        'status' => $candidate->update(['status' => 'PAUSED']),
        'kind' => DB::table('whatsapp_templates')->where('id', $candidate->id)->update(['kind' => 'legacy']),
        'meta_name' => $candidate->update(['meta_template_name' => '   ']),
        'language' => $candidate->update(['language' => '   ']),
        'template_waba' => $candidate->update(['meta_waba_id' => null]),
    };

    $response = $this->actingAs($user)->patch("/campanhas/{$campaign->id}", [
        'whatsapp_instance_id' => $candidate->whatsapp_instance_id,
        'whatsapp_template_id' => $candidate->id,
    ]);

    $response->assertInvalid(['whatsapp_template_id']);
    expect($campaign->fresh()->whatsapp_instance_id)->toBe($campaignInstance->id)
        ->and($campaign->fresh()->whatsapp_template_id)->toBe($currentTemplate->id);
})->with([
    'different instance even when the request forges that instance' => 'instance',
    'different WABA' => 'waba',
    'missing instance WABA' => 'instance_waba',
    'different tenant' => 'tenant',
    'not approved' => 'status',
    'not Meta HSM' => 'kind',
    'missing Meta name' => 'meta_name',
    'missing language' => 'language',
    'missing template WABA' => 'template_waba',
]);

test('update accepts a compatible replacement template without changing the instance', function () {
    $user = userWithTenant();
    $instance = phase09CampaignInstance($user, 'waba-update');
    $currentTemplate = phase09CampaignTemplate($user, $instance, [
        'meta_template_name' => 'template_atual',
    ]);
    $replacement = phase09CampaignTemplate($user, $instance, [
        'meta_template_name' => 'template_novo',
    ]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $currentTemplate->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)->patch("/campanhas/{$campaign->id}", [
        'whatsapp_template_id' => $replacement->id,
    ])->assertSessionHasNoErrors();

    expect($campaign->fresh()->whatsapp_instance_id)->toBe($instance->id)
        ->and($campaign->fresh()->whatsapp_template_id)->toBe($replacement->id);
});
