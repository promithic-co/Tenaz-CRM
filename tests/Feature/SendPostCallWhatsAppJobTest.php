<?php

use App\Jobs\SendPostCallWhatsAppJob;
use App\Models\Lead;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignCall;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->whatsappService = $this->mock(WhatsAppService::class);
});

function makeCallWithMetaTemplate(): array
{
    $user = userWithTenant();
    $tenant = $user->tenants()->first();
    $tenantId = (string) $tenant->id;

    $whatsappInstance = WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => $tenantId,
        'agent_id' => null,
    ]);

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'kind' => 'meta_hsm',
        'status' => 'APPROVED',
        'meta_template_name' => 'post_call_template',
        'language' => 'pt_BR',
    ]);

    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'post_call_message' => null,
        'post_call_meta_template_id' => $template->id,
    ]);

    $campaign = VoiceCampaign::factory()->create([
        'tenant_id' => $tenantId,
        'voice_instance_id' => $voiceInstance->id,
        'post_call_message' => null,
    ]);

    $call = VoiceCampaignCall::factory()->create([
        'voice_campaign_id' => $campaign->id,
        'phone' => '+5511987654321',
        'status' => 'interested',
    ]);

    return [$call, $template];
}

test('new contact: lead created and whatsapp template sent', function () {
    $this->whatsappService->shouldReceive('sendTemplateViaInstance')->once();

    [$call] = makeCallWithMetaTemplate();

    (new SendPostCallWhatsAppJob($call->id))->handle($this->whatsappService);

    expect(Lead::where('whatsapp', '5511987654321')->count())->toBe(1);
    $lead = Lead::where('whatsapp', '5511987654321')->first();
    expect($lead->modo)->toBe('receptivo');
});

test('retry does not re-send the template (idempotency claim)', function () {
    $this->whatsappService->shouldReceive('sendTemplateViaInstance')->once();

    [$call] = makeCallWithMetaTemplate();

    $job = new SendPostCallWhatsAppJob($call->id);
    $job->handle($this->whatsappService);
    $job->handle($this->whatsappService);
});

test('send failure releases the idempotency claim so a retry re-attempts the send (ATOM-3)', function () {
    [$call] = makeCallWithMetaTemplate();

    $this->whatsappService->shouldReceive('sendTemplateViaInstance')
        ->once()->ordered()->andThrow(new RuntimeException('transient meta error'));
    $this->whatsappService->shouldReceive('sendTemplateViaInstance')
        ->once()->ordered()->andReturn('wamid.ok');

    $job = new SendPostCallWhatsAppJob($call->id);
    $claimKey = "postcall_send:{$call->id}";

    // First attempt fails mid-send and must propagate so the queue records/retries it.
    expect(fn () => $job->handle($this->whatsappService))->toThrow(RuntimeException::class);
    expect(Cache::has($claimKey))->toBeFalse();

    // Claim was released, so the retry actually re-sends (the ->twice() expectation proves
    // the second send fired rather than short-circuiting on a stale claim).
    $job->handle($this->whatsappService);
    expect(Cache::has($claimKey))->toBeTrue();
});

test('existing lead: reused, no duplicate, template sent', function () {
    $this->whatsappService->shouldReceive('sendTemplateViaInstance')->once();

    [$call] = makeCallWithMetaTemplate();
    $voiceInstance = $call->voiceCampaign->voiceInstance;

    Lead::factory()->create([
        'tenant_id' => $voiceInstance->tenant_id,
        'whatsapp' => '5511987654321',
    ]);

    $beforeCount = Lead::count();

    (new SendPostCallWhatsAppJob($call->id))->handle($this->whatsappService);

    expect(Lead::count())->toBe($beforeCount);
});

test('voice instance without whatsapp instance: job aborts silently', function () {
    $this->whatsappService->shouldNotReceive('sendTemplateViaInstance');
    Log::shouldReceive('warning')->once()->with('ivr.no_whatsapp_instance', Mockery::any());

    $user = userWithTenant();
    $tenant = $user->tenants()->first();
    $tenantId = (string) $tenant->id;

    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => null,
    ]);
    $campaign = VoiceCampaign::factory()->create([
        'tenant_id' => $tenantId,
        'voice_instance_id' => $voiceInstance->id,
    ]);
    $call = VoiceCampaignCall::factory()->create([
        'voice_campaign_id' => $campaign->id,
        'status' => 'interested',
    ]);

    (new SendPostCallWhatsAppJob($call->id))->handle($this->whatsappService);

    expect(Lead::count())->toBe(0);
});

test('no approved template: job logs warning and skips send', function () {
    $this->whatsappService->shouldNotReceive('sendTemplateViaInstance');

    $user = userWithTenant();
    $tenant = $user->tenants()->first();
    $tenantId = (string) $tenant->id;

    $whatsappInstance = WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => $tenantId,
        'agent_id' => null,
    ]);

    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'post_call_meta_template_id' => null,
    ]);

    $campaign = VoiceCampaign::factory()->create([
        'tenant_id' => $tenantId,
        'voice_instance_id' => $voiceInstance->id,
    ]);
    $call = VoiceCampaignCall::factory()->create([
        'voice_campaign_id' => $campaign->id,
        'phone' => '+5511111111111',
        'status' => 'interested',
    ]);

    (new SendPostCallWhatsAppJob($call->id))->handle($this->whatsappService);

    // Lead is still created even when no template — CRM persistence is not blocked
    expect(Lead::where('whatsapp', '5511111111111')->count())->toBe(1);
});
