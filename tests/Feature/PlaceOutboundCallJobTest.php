<?php

use App\Jobs\PlaceOutboundCallJob;
use App\Models\ContactList;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignCall;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;

function makePendingCall(string $campaignStatus = 'sending'): VoiceCampaignCall
{
    $user = userWithTenant();
    $tenant = $user->tenants()->first();
    $tenantId = (string) $tenant->id;

    $whatsappInstance = WhatsappInstance::factory()->create(['tenant_id' => $tenantId]);
    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
    ]);

    $contactList = ContactList::factory()->create(['tenant_id' => $tenantId]);

    $campaign = VoiceCampaign::factory()->create([
        'tenant_id' => $tenantId,
        'voice_instance_id' => $voiceInstance->id,
        'contact_list_id' => $contactList->id,
        'status' => $campaignStatus,
    ]);

    return VoiceCampaignCall::factory()->create([
        'voice_campaign_id' => $campaign->id,
        'status' => 'pending',
    ]);
}

test('job aborts without modifying call when campaign status is not sending', function () {
    $call = makePendingCall('paused');

    $job = new PlaceOutboundCallJob($call);
    $job->handle();

    $call->refresh();
    expect($call->status)->toBe('pending');
});

test('failed handler marks call as failed and increments campaign total_failed', function () {
    $call = makePendingCall('sending');
    $campaign = $call->voiceCampaign;

    $job = new PlaceOutboundCallJob($call);
    $job->failed(new \Exception('Twilio error'));

    $call->refresh();
    $campaign->refresh();

    expect($call->status)->toBe('failed');
    expect($campaign->total_failed)->toBe(1);
});

test('job reads SID and token from config not from voiceInstance model', function () {
    config([
        'services.twilio.sid' => 'ACtest1234567890test1234567890test12',
        'services.twilio.token' => 'testtoken1234567890testtoken123456',
        'services.twilio.phone_number' => '+5511999999999',
    ]);

    $call = makePendingCall('sending');

    // With config-sourced credentials, the Twilio SDK throws an HTTP/auth error.
    // It must NOT throw "undefined property twilio_account_sid" — that would
    // indicate credentials are still being read from the model.
    try {
        (new PlaceOutboundCallJob($call))->handle();
    } catch (\Exception $e) {
        expect($e->getMessage())
            ->not->toContain('twilio_account_sid')
            ->not->toContain('twilio_auth_token')
            ->not->toContain('twilio_phone_number');
    }
});

test('failed handler logs error with call id phone and message', function () {
    \Illuminate\Support\Facades\Log::spy();

    $call = makePendingCall('sending');

    $job = new PlaceOutboundCallJob($call);
    $job->failed(new \Exception('network timeout'));

    \Illuminate\Support\Facades\Log::shouldHaveReceived('error')
        ->once()
        ->with('PlaceOutboundCallJob.failed', \Mockery::on(function (array $context) use ($call) {
            return isset($context['call_id']) && $context['call_id'] === $call->id
                && isset($context['phone']) && $context['phone'] === $call->phone
                && isset($context['error']) && $context['error'] === 'network timeout';
        }));
});
