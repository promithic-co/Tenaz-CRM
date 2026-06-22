<?php

use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Jobs\ProcessWhatsappOutboxMessageJob;
use App\Models\Agent;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Services\AgentService;
use App\Services\CampaignReplyDetector;
use App\Services\ConversationAutomationService;
use App\Services\ConversationTimelineService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

test('inbound agent turn writes unified timeline and queues whatsapp outbox', function () {
    Event::fake();

    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    WhatsappInstance::factory()->for($user)->create([
        'name' => 'timeline-evo',
        'agent_id' => $agent->id,
    ]);

    $this->mock(AgentService::class, function ($mock) {
        $mock->shouldReceive('process')->once()->andReturn('Resposta da IA');
    });
    $this->mock(CampaignReplyDetector::class, function ($mock) {
        $mock->shouldReceive('detect')->once()->andReturnNull();
    });

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: '5511999990001',
        name: 'Cliente Teste',
        tenantId: (string) $user->tenantId,
        agentId: $agent->id,
        instanceName: 'timeline-evo',
        aggregatedMessage: 'Oi',
        providerMessageId: 'wamid.inbound',
    );

    app()->call([$job, 'handle']);

    $lead = Lead::where('whatsapp', '5511999990001')->firstOrFail();

    $this->assertDatabaseHas('conversation_timeline_messages', [
        'lead_id' => $lead->id,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'status' => 'received',
        'provider_message_id' => 'wamid.inbound',
    ]);

    $this->assertDatabaseHas('conversation_timeline_messages', [
        'lead_id' => $lead->id,
        'direction' => 'outbound',
        'sender_type' => 'agent',
        'status' => 'queued',
        'source' => 'agent',
    ]);

    $this->assertDatabaseHas('whatsapp_outbox_messages', [
        'lead_id' => $lead->id,
        'status' => 'queued',
    ]);

    expect(WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->count())->toBe(1);
});

test('manual ai mode records inbound without calling agent', function () {
    Event::fake();

    $user = User::factory()->create();
    WhatsappInstance::factory()->for($user)->create([
        'name' => 'manual-evo',
        'default_ai_mode' => 'manual',
    ]);

    $this->mock(AgentService::class, function ($mock) {
        $mock->shouldReceive('process')->never();
    });
    $this->mock(CampaignReplyDetector::class, function ($mock) {
        $mock->shouldReceive('detect')->once()->andReturnNull();
    });

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: '5511999990002',
        name: 'Cliente Manual',
        tenantId: (string) $user->id,
        agentId: null,
        instanceName: 'manual-evo',
        aggregatedMessage: 'Quero falar com atendimento',
        providerMessageId: 'wamid.manual',
    );

    app()->call([$job, 'handle']);

    $lead = Lead::where('whatsapp', '5511999990002')->firstOrFail();

    expect($lead->fresh()->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);

    $this->assertDatabaseHas('conversation_timeline_messages', [
        'lead_id' => $lead->id,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'provider_message_id' => 'wamid.manual',
    ]);

    $this->assertDatabaseMissing('whatsapp_outbox_messages', [
        'lead_id' => $lead->id,
    ]);
});

test('inactive agent does not hide inbound from timeline', function () {
    Event::fake();

    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'is_active' => false,
    ]);
    WhatsappInstance::factory()->for($user)->create([
        'name' => 'inactive-agent-evo',
        'agent_id' => $agent->id,
    ]);

    $this->mock(AgentService::class, function ($mock) {
        $mock->shouldReceive('process')->never();
    });
    $this->mock(CampaignReplyDetector::class, function ($mock) {
        $mock->shouldReceive('detect')->once()->andReturnNull();
    });

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: '5511999990003',
        name: 'Cliente Sem IA',
        tenantId: (string) $user->id,
        agentId: $agent->id,
        instanceName: 'inactive-agent-evo',
        aggregatedMessage: 'Oi',
        providerMessageId: 'wamid.inactive',
    );

    app()->call([$job, 'handle']);

    $lead = Lead::where('whatsapp', '5511999990003')->firstOrFail();

    $this->assertDatabaseHas('conversation_timeline_messages', [
        'lead_id' => $lead->id,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'provider_message_id' => 'wamid.inactive',
    ]);

    $this->assertDatabaseMissing('whatsapp_outbox_messages', [
        'lead_id' => $lead->id,
    ]);
});

test('inbound on instance without agent persists CRM lead with null agent_id and skips AI', function () {
    Event::fake();

    $user = User::factory()->create();
    WhatsappInstance::factory()->for($user)->create([
        'name' => 'crm-only-evo',
        'agent_id' => null,
        'default_ai_mode' => 'automatic',
    ]);

    $this->mock(AgentService::class, function ($mock) {
        $mock->shouldReceive('process')->never();
    });
    $this->mock(CampaignReplyDetector::class, function ($mock) {
        $mock->shouldReceive('detect')->once()->andReturnNull();
    });

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: '5511999990010',
        name: 'Cliente CRM',
        tenantId: (string) $user->tenantId,
        agentId: null,
        instanceName: 'crm-only-evo',
        aggregatedMessage: 'Olá, gostaria de falar com um atendente',
        providerMessageId: 'wamid.crm-only',
    );

    app()->call([$job, 'handle']);

    $lead = Lead::where('whatsapp', '5511999990010')->firstOrFail();

    expect($lead->agent_id)->toBeNull()
        ->and($lead->fresh()->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);

    $this->assertDatabaseHas('conversation_timeline_messages', [
        'lead_id' => $lead->id,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'provider_message_id' => 'wamid.crm-only',
    ]);

    $this->assertDatabaseHas('agent_interaction_events', [
        'lead_id' => $lead->id,
        'event_type' => 'automation_skipped_no_agent',
    ]);

    $this->assertDatabaseMissing('whatsapp_outbox_messages', [
        'lead_id' => $lead->id,
    ]);
});

test('CRM-only lead is forced into manual mode regardless of instance default', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->withoutAgent()->create([
        'tenant_id' => (string) $user->tenantId,
    ]);
    WhatsappInstance::factory()->for($user)->create([
        'name' => 'auto-default-evo',
        'agent_id' => null,
        'default_ai_mode' => 'automatic',
    ]);

    $automation = app(ConversationAutomationService::class);

    expect($automation->resolveMode($lead, 'auto-default-evo'))->toBe(Lead::AI_MODE_MANUAL)
        ->and($automation->shouldAutoRespond($lead, 'auto-default-evo'))->toBeFalse();
});

test('duplicate webhook delivery with same provider_message_id is idempotent', function () {
    Event::fake();

    $user = User::factory()->create();
    WhatsappInstance::factory()->for($user)->create([
        'name' => 'idem-evo',
        'agent_id' => null,
        'default_ai_mode' => 'manual',
    ]);

    $this->mock(AgentService::class, function ($mock) {
        $mock->shouldReceive('process')->never();
    });
    $this->mock(CampaignReplyDetector::class, function ($mock) {
        // detector should run only once across both deliveries
        $mock->shouldReceive('detect')->once()->andReturnNull();
    });

    $payload = [
        'phone' => '5511999990020',
        'name' => 'Cliente Idem',
        'tenantId' => (string) $user->tenantId,
        'agentId' => null,
        'instanceName' => 'idem-evo',
        'aggregatedMessage' => 'Mensagem idempotente',
        'providerMessageId' => 'wamid.idem-1',
    ];

    app()->call([new ProcessIncomingWhatsAppMessageJob(...$payload), 'handle']);
    app()->call([new ProcessIncomingWhatsAppMessageJob(...$payload), 'handle']);

    $lead = Lead::where('whatsapp', '5511999990020')->firstOrFail();

    expect(ConversationTimelineMessage::query()
        ->where('lead_id', $lead->id)
        ->where('provider_message_id', 'wamid.idem-1')
        ->count())->toBe(1);
});

test('incoming whatsapp processing has a retry window for lock releases', function () {
    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: '5511999990021',
        name: 'Cliente Retry',
        tenantId: '1',
        agentId: null,
        instanceName: 'retry-evo',
        aggregatedMessage: 'Mensagem',
    );

    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class);
    expect($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp());
    expect($job->maxExceptions)->toBe(2);
});

test('outbox processing has a retry window for scheduled releases', function () {
    $job = new ProcessWhatsappOutboxMessageJob(1);

    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class);
    expect($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp());
    expect($job->maxExceptions)->toBe(3);
});

test('outbox processing dispatches only after the creating transaction commits (SCALE-12)', function () {
    // The outbox row is written inside the caller's transaction; the worker must not start
    // until that commit, or its find() null-guard burns a spurious retry on a not-yet-visible row.
    expect((new ProcessWhatsappOutboxMessageJob(1))->afterCommit)->toBeTrue();
});

test('outbox processor marks timeline sent with provider id', function () {
    Event::fake();
    Http::fake([
        'https://graph.facebook.com/*/messages' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'meta.sent.1']],
        ], 200),
    ]);

    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->for($user)->create([
        'name' => 'test-instance',
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $timeline = app(ConversationTimelineService::class)->record(
        lead: $lead,
        direction: 'outbound',
        senderType: 'human',
        body: 'Mensagem',
        status: 'queued',
        source: 'manual',
    );

    $outbox = WhatsappOutboxMessage::create([
        'tenant_id' => (string) $user->tenantId,
        'lead_id' => $lead->id,
        'channel' => 'whatsapp',
        'provider' => 'meta_cloud',
        'payload_json' => [
            'type' => 'text',
            'instance_id' => $instance->id,
            'instance_name' => 'test-instance',
            'phone' => $lead->whatsapp,
            'text' => 'Mensagem',
        ],
        'status' => 'queued',
        'idempotency_key' => 'manual-test-key',
        'scheduled_at' => now(),
        'timeline_message_id' => $timeline->id,
    ]);

    app()->call([new ProcessWhatsappOutboxMessageJob($outbox->id), 'handle']);

    expect($outbox->fresh()->status)->toBe('sent')
        ->and($outbox->fresh()->provider_message_id)->toBe('meta.sent.1')
        ->and($timeline->fresh()->status)->toBe('sent')
        ->and($timeline->fresh()->provider_message_id)->toBe('meta.sent.1');
});
