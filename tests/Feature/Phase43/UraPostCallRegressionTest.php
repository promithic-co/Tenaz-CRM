<?php

use App\Jobs\SendPostCallWhatsAppJob;
use App\Models\ContactList;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignCall;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppService;

it('test_ura_post_call_via_meta_cloud_instance', function () {
    $user = userWithTenant();
    $tenant = $user->tenants()->first();

    $whatsappInstance = WhatsappInstance::factory()->metaCloud()->for($user)->create([
        'name' => 'meta-ura',
        'tenant_id' => (string) $tenant->id,
    ]);

    $voiceInstance = VoiceInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'post_call_message' => 'Obrigado pelo interesse!',
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'meta_template_name' => 'post_call_interest',
        'language' => 'pt_BR',
        'status' => 'APPROVED',
    ]);
    $voiceInstance->update(['post_call_meta_template_id' => $template->id]);

    $list = ContactList::factory()->create(['tenant_id' => $tenant->id]);

    $campaign = VoiceCampaign::factory()->create([
        'voice_instance_id' => $voiceInstance->id,
        'contact_list_id' => $list->id,
        'tenant_id' => $tenant->id,
        'post_call_message' => null,
    ]);

    $call = VoiceCampaignCall::factory()->create([
        'voice_campaign_id' => $campaign->id,
        'phone' => '+5511999990002',
        'status' => 'interested',
    ]);

    $capturedInstance = null;

    $whatsappMock = Mockery::mock(WhatsAppService::class);
    $whatsappMock->shouldReceive('sendTemplateViaInstance')
        ->once()
        ->withArgs(function (WhatsappInstance $instance, string $phone, string $templateName) use (&$capturedInstance): bool {
            $capturedInstance = $instance;

            return $templateName === 'post_call_interest';
        });
    app()->instance(WhatsAppService::class, $whatsappMock);

    $job = new SendPostCallWhatsAppJob($call->id);
    app()->call([$job, 'handle']);

    expect($capturedInstance)->not->toBeNull();
    expect($capturedInstance->id)->toBe($whatsappInstance->id);
    expect($capturedInstance->provider->value)->toBe('meta_cloud');
});
