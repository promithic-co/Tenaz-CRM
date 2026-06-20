<?php

use App\Jobs\PlaceOutboundCallJob;
use App\Models\ContactList;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignCall;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
    $job->failed(new Exception('Twilio error'));

    $call->refresh();
    $campaign->refresh();

    expect($call->status)->toBe('failed');
    expect($campaign->total_failed)->toBe(1);
});

test('does not place call when cache claim already held (lost-response retry guard)', function () {
    $call = makePendingCall('sending');
    Cache::add("voice_call_place:{$call->id}", 1, now()->addMinutes(10));

    (new PlaceOutboundCallJob($call))->handle();

    $call->refresh();
    expect($call->status)->toBe('pending');
    expect($call->call_sid)->toBeNull();
});

test('does not place call when call_sid already persisted', function () {
    $call = makePendingCall('sending');
    $call->update(['call_sid' => 'CAalreadyplaced', 'status' => 'calling']);

    (new PlaceOutboundCallJob($call))->handle();

    $call->refresh();
    expect($call->call_sid)->toBe('CAalreadyplaced');
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
    } catch (Exception $e) {
        expect($e->getMessage())
            ->not->toContain('twilio_account_sid')
            ->not->toContain('twilio_auth_token')
            ->not->toContain('twilio_phone_number');
    }
});

test('failed handler does not double-count when call already terminal (REL-3)', function () {
    $call = makePendingCall('sending');
    $campaign = $call->voiceCampaign;

    // Twilio status callback already moved the call to a terminal status and counted it.
    $call->update(['status' => 'no_answer']);
    $campaign->increment('total_no_answer');

    (new PlaceOutboundCallJob($call))->failed(new Exception('late job failure'));

    $call->refresh();
    $campaign->refresh();

    expect($call->status)->toBe('no_answer');
    expect($campaign->total_failed)->toBe(0);
});

test('failed handler logs error with call id phone and message', function () {
    Log::spy();

    $call = makePendingCall('sending');

    $job = new PlaceOutboundCallJob($call);
    $job->failed(new Exception('network timeout'));

    Log::shouldHaveReceived('error')
        ->once()
        ->with('PlaceOutboundCallJob.failed', Mockery::on(function (array $context) use ($call) {
            return isset($context['call_id']) && $context['call_id'] === $call->id
                && isset($context['phone']) && $context['phone'] === $call->phone
                && isset($context['error']) && $context['error'] === 'network timeout';
        }));
});
