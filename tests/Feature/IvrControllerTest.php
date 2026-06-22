<?php

use App\Http\Middleware\ValidateTwilioSignature;
use App\Jobs\SendPostCallWhatsAppJob;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignCall;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    // Bypass Twilio signature verification in tests
    $this->withoutMiddleware(ValidateTwilioSignature::class);
});

function makeIvrCall(array $callOverrides = []): VoiceCampaignCall
{
    $user = userWithTenant();
    $tenant = $user->tenants()->first();
    $tenantId = (string) $tenant->id;

    $whatsappInstance = WhatsappInstance::factory()->create(['tenant_id' => $tenantId]);

    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
    ]);

    $campaign = VoiceCampaign::factory()->create([
        'tenant_id' => $tenantId,
        'voice_instance_id' => $voiceInstance->id,
        'status' => 'sending',
        'total_calls' => 1,
    ]);

    return VoiceCampaignCall::factory()->create(array_merge([
        'voice_campaign_id' => $campaign->id,
        'interpolated_message' => 'Olá João, pressione 1 para saber mais.',
    ], $callOverrides));
}

test('script returns TwiML with pt-BR Say and Gather', function () {
    $call = makeIvrCall();

    $response = $this->post(route('ivr.script', $call));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    $this->assertStringContainsString('<Say language="pt-BR"', $response->getContent());
    $this->assertStringContainsString('Olá João, pressione 1 para saber mais.', $response->getContent());
    $this->assertStringContainsString('<Gather', $response->getContent());

    $call->refresh();
    expect($call->status)->toBe('answered');
});

test('replayed script callback increments total_answered only once', function () {
    $call = makeIvrCall();

    $this->post(route('ivr.script', $call))->assertSuccessful();
    $this->post(route('ivr.script', $call))->assertSuccessful();

    $call->refresh();
    $call->voiceCampaign->refresh();

    expect($call->status)->toBe('answered')
        ->and($call->voiceCampaign->total_answered)->toBe(1);
});

test('dtmf digit 1 sets call status to interested', function () {
    $call = makeIvrCall(['status' => 'answered']);

    $response = $this->post(route('ivr.dtmf', $call), ['Digits' => '1']);

    $response->assertStatus(200);
    $this->assertStringContainsString('<Hangup', $response->getContent());

    $call->refresh();
    expect($call->status)->toBe('interested');
});

test('replayed interested dtmf increments total_interested only once', function () {
    $call = makeIvrCall(['status' => 'answered']);

    $this->post(route('ivr.dtmf', $call), ['Digits' => '1'])->assertSuccessful();
    $this->post(route('ivr.dtmf', $call), ['Digits' => '1'])->assertSuccessful();

    $call->refresh();
    $call->voiceCampaign->refresh();

    expect($call->status)->toBe('interested')
        ->and($call->voiceCampaign->total_interested)->toBe(1);
});

test('dtmf digit 2 sets call status to optout', function () {
    $call = makeIvrCall(['status' => 'answered']);

    $response = $this->post(route('ivr.dtmf', $call), ['Digits' => '2']);

    $response->assertStatus(200);
    $this->assertStringContainsString('<Hangup', $response->getContent());

    $call->refresh();
    expect($call->status)->toBe('optout');
});

test('status callback completed + interested dispatches SendPostCallWhatsAppJob', function () {
    $call = makeIvrCall(['status' => 'interested']);

    $response = $this->post(route('ivr.status', $call), ['CallStatus' => 'completed']);

    $response->assertStatus(204);
    Queue::assertPushed(SendPostCallWhatsAppJob::class, fn ($job) => $job->voiceCampaignCallId === $call->id);
});

test('status callback no-answer sets call status and does not dispatch job', function () {
    $call = makeIvrCall(['status' => 'calling']);

    $response = $this->post(route('ivr.status', $call), ['CallStatus' => 'no-answer']);

    $response->assertStatus(204);
    Queue::assertNotPushed(SendPostCallWhatsAppJob::class);

    $call->refresh();
    expect($call->status)->toBe('no_answer');

    $call->voiceCampaign->refresh();
    expect($call->voiceCampaign->total_no_answer)->toBe(1);
});

test('replayed no-answer callback increments total_no_answer only once (REL-3)', function () {
    $call = makeIvrCall(['status' => 'calling']);

    $this->post(route('ivr.status', $call), ['CallStatus' => 'no-answer'])->assertStatus(204);
    $this->post(route('ivr.status', $call), ['CallStatus' => 'no-answer'])->assertStatus(204);

    $call->voiceCampaign->refresh();
    expect($call->voiceCampaign->total_no_answer)->toBe(1);
});

test('replayed failed callback increments total_failed only once (REL-3)', function () {
    $call = makeIvrCall(['status' => 'calling']);

    $this->post(route('ivr.status', $call), ['CallStatus' => 'failed'])->assertStatus(204);
    $this->post(route('ivr.status', $call), ['CallStatus' => 'busy'])->assertStatus(204);

    $call->refresh();
    expect($call->status)->toBe('failed');

    $call->voiceCampaign->refresh();
    expect($call->voiceCampaign->total_failed)->toBe(1);
});
