<?php

use App\Jobs\SendInboundLeadWhatsAppJob;
use App\Jobs\SendUraTemplateJob;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\UraApiKey;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppService;

beforeEach(function () {
    $this->whatsappService = $this->mock(WhatsAppService::class);
});

test('inbound lead job retry does not re-send the meta template', function () {
    $this->whatsappService->shouldReceive('sendTemplateViaInstance')->once();

    $user = userWithTenant();
    $tenantId = (string) $user->tenants()->first()->id;

    $whatsappInstance = WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => $tenantId,
        'agent_id' => null,
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'inbound_tpl',
        'language' => 'pt_BR',
    ]);
    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'post_call_meta_template_id' => $template->id,
    ]);

    $job = new SendInboundLeadWhatsAppJob($voiceInstance->id, '+5511987654321', 'Test');
    $job->handle($this->whatsappService);
    $job->handle($this->whatsappService);
});

test('ura template job retry does not re-send the meta template', function () {
    $this->whatsappService->shouldReceive('sendTemplateViaInstance')->once();

    $user = userWithTenant();
    $tenantId = (string) $user->tenants()->first()->id;

    $agent = Agent::factory()->create(['tenant_id' => $tenantId]);
    $whatsappInstance = WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => $tenantId,
        'agent_id' => $agent->id,
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'trigger_tpl',
        'language' => 'pt_BR',
    ]);
    $generated = UraApiKey::generate();
    $apiKey = UraApiKey::create([
        'tenant_id' => $tenantId,
        'agent_id' => $agent->id,
        'whatsapp_template_id' => $template->id,
        'name' => 'Idempotency key',
        'key_hash' => $generated['key_hash'],
        'key_preview' => $generated['key_preview'],
        'active' => true,
    ]);

    $job = new SendUraTemplateJob($apiKey->id, '+5511987654321', 'Test', []);
    $job->handle($this->whatsappService);
    $job->handle($this->whatsappService);
});

test('ura template job rejects a foreign agent before creating lead or contact', function () {
    $this->whatsappService->shouldNotReceive('sendTemplateViaInstance');

    $tenant = userWithTenant()->tenants()->firstOrFail();
    $foreignTenant = userWithTenant()->tenants()->firstOrFail();
    $agent = Agent::factory()->create(['tenant_id' => (string) $foreignTenant->id]);
    WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => (string) $foreignTenant->id,
        'agent_id' => $agent->id,
    ]);
    $apiKey = UraApiKey::factory()->create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
    ]);

    (new SendUraTemplateJob($apiKey->id, '+5511987654301', 'Foreign agent'))->handle($this->whatsappService);

    expect(Lead::withoutGlobalScopes()->count())->toBe(0)
        ->and(Contact::withoutGlobalScopes()->count())->toBe(0);
});

test('ura template job rejects a foreign template before creating lead or contact', function () {
    $this->whatsappService->shouldNotReceive('sendTemplateViaInstance');

    $tenant = userWithTenant()->tenants()->firstOrFail();
    $foreignTenant = userWithTenant()->tenants()->firstOrFail();
    $agent = Agent::factory()->create(['tenant_id' => (string) $tenant->id]);
    WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => (string) $tenant->id,
        'agent_id' => $agent->id,
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $foreignTenant->id,
        'status' => 'APPROVED',
    ]);
    $apiKey = UraApiKey::factory()->create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'whatsapp_template_id' => $template->id,
    ]);

    (new SendUraTemplateJob($apiKey->id, '+5511987654302', 'Foreign template'))->handle($this->whatsappService);

    expect(Lead::withoutGlobalScopes()->count())->toBe(0)
        ->and(Contact::withoutGlobalScopes()->count())->toBe(0);
});

test('ura template job rejects a foreign whatsapp instance before creating lead or contact', function () {
    $this->whatsappService->shouldNotReceive('sendTemplateViaInstance');

    $tenant = userWithTenant()->tenants()->firstOrFail();
    $foreignTenant = userWithTenant()->tenants()->firstOrFail();
    $agent = Agent::factory()->create(['tenant_id' => (string) $tenant->id]);
    WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => (string) $foreignTenant->id,
        'agent_id' => $agent->id,
    ]);
    $apiKey = UraApiKey::factory()->create([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
    ]);

    (new SendUraTemplateJob($apiKey->id, '+5511987654303', 'Foreign instance'))->handle($this->whatsappService);

    expect(Lead::withoutGlobalScopes()->count())->toBe(0)
        ->and(Contact::withoutGlobalScopes()->count())->toBe(0);
});
