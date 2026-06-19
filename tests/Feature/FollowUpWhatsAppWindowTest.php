<?php

use App\Ai\Agents\CredFlowFollowUpAgent;
use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Jobs\ProcessLeadFollowUpJob;
use App\Jobs\ProcessWhatsappOutboxMessageJob;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Services\FollowUpWindowService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('follow-up is blocked after normal WhatsApp window expires and requires HSM', function (): void {
    $this->travelTo(Carbon::parse('2026-05-29 15:00:00', 'UTC'));

    $lead = Lead::factory()->create([
        'followup_status' => 'active',
        'last_inbound_at' => now()->subHours(25),
        'service_window_expires_at' => now()->subHour(),
        'free_entry_point_expires_at' => null,
    ]);

    $evaluation = app(FollowUpWindowService::class)->evaluate($lead, [
        'enabled' => true,
        'business_window_start' => '00:00',
        'business_window_end' => '23:59',
        'first_delay_minutes' => 10,
        'max_attempts_within_window' => 2,
    ]);

    expect($evaluation['eligible'])->toBeFalse()
        ->and($evaluation['reason'])->toBe('window_expired_requires_hsm');
});

test('follow-up can run inside eligible 72h free entry point window', function (): void {
    $this->travelTo(Carbon::parse('2026-05-29 15:00:00', 'UTC'));

    $lead = Lead::factory()->create([
        'followup_status' => 'active',
        'last_inbound_at' => now()->subHours(30),
        'service_window_expires_at' => now()->subHours(6),
        'free_entry_point_started_at' => now()->subHours(30),
        'free_entry_point_expires_at' => now()->addHours(42),
        'conversation_window_source' => 'ctwa_ad',
    ]);

    $evaluation = app(FollowUpWindowService::class)->evaluate($lead, [
        'enabled' => true,
        'business_window_start' => '00:00',
        'business_window_end' => '23:59',
        'first_delay_minutes' => 10,
        'max_attempts_within_window' => 2,
    ]);

    expect($evaluation['eligible'])->toBeTrue()
        ->and($evaluation['window_expires_at'])->toBe($lead->free_entry_point_expires_at->toIso8601String());
});

test('inbound referral persists WhatsApp service and free entry point windows', function (): void {
    $now = Carbon::parse('2026-05-29 15:00:00', 'UTC');
    $this->travelTo($now);

    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => 'default',
        'name' => 'meta-main',
    ]);

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: '5511999999999',
        name: 'Lead CTWA',
        tenantId: 'default',
        agentId: null,
        instanceName: $instance->name,
        aggregatedMessage: 'Oi, vim pelo anuncio',
        mediaContext: null,
        interactionId: 'interaction-ctwa',
        providerMessageId: 'wamid.ctwa',
        mediaPayload: null,
        referral: [
            'source_type' => 'ad',
            'source_id' => 'ad-123',
            'ctwa_clid' => 'clid-123',
        ],
    );

    app()->call([$job, 'handle']);

    $lead = Lead::query()->where('whatsapp', '5511999999999')->firstOrFail();

    expect($lead->service_window_expires_at?->getTimestamp())->toBe($now->copy()->addHours(24)->getTimestamp())
        ->and($lead->free_entry_point_started_at?->getTimestamp())->toBe($now->getTimestamp())
        ->and($lead->free_entry_point_expires_at?->getTimestamp())->toBe($now->copy()->addHours(72)->getTimestamp())
        ->and($lead->conversation_window_source)->toBe('ctwa_ad')
        ->and($lead->whatsapp_instance_id)->toBe($instance->id);
});

test('follow-up queues WhatsApp outbox through lead instance id', function (): void {
    $this->travelTo(Carbon::parse('2026-05-29 15:00:00', 'UTC'));
    CredFlowFollowUpAgent::fake(['Mensagem de follow-up']);

    $agent = Agent::factory()->create(['tenant_id' => 'default']);
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => 'default',
        'agent_id' => $agent->id,
        'name' => 'instance-correta',
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'followup_status' => 'active',
        'followup_count' => 0,
        'last_inbound_at' => now()->subHours(2),
        'service_window_expires_at' => now()->addHours(22),
        'whatsapp_instance_id' => $instance->id,
    ]);

    $job = new ProcessLeadFollowUpJob($lead);
    $job->handle(
        app(\App\Services\WhatsappOutboxService::class),
        app(\App\Services\FollowUpSettingsResolver::class),
        app(\App\Services\FollowUpWindowService::class),
        app(\App\Services\PauseService::class),
    );

    $outbox = WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->firstOrFail();

    expect($outbox->payload_json['instance_id'])->toBe($instance->id)
        ->and($outbox->payload_json['instance_name'])->toBe('instance-correta')
        ->and($lead->fresh()->followup_count)->toBe(1);
});

test('follow-up does not send without lead WhatsApp instance id', function (): void {
    $this->travelTo(Carbon::parse('2026-05-29 15:00:00', 'UTC'));
    CredFlowFollowUpAgent::fake(['Mensagem de follow-up']);

    $lead = Lead::factory()->create([
        'followup_status' => 'active',
        'followup_count' => 0,
        'last_inbound_at' => now()->subHours(2),
        'service_window_expires_at' => now()->addHours(22),
        'whatsapp_instance_id' => null,
    ]);

    $job = new ProcessLeadFollowUpJob($lead);
    $job->handle(
        app(\App\Services\WhatsappOutboxService::class),
        app(\App\Services\FollowUpSettingsResolver::class),
        app(\App\Services\FollowUpWindowService::class),
        app(\App\Services\PauseService::class),
    );

    expect(WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->exists())->toBeFalse()
        ->and($lead->fresh()->followup_count)->toBe(0);
});

test('outbox is not marked sent when provider returns no message confirmation', function (): void {
    $instance = WhatsappInstance::factory()->create(['name' => 'meta-main']);
    $lead = Lead::factory()->create([
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $outbox = WhatsappOutboxMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'channel' => 'whatsapp',
        'provider' => 'meta_cloud',
        'payload_json' => [
            'type' => 'text',
            'instance_id' => $instance->id,
            'instance_name' => $instance->name,
            'phone' => $lead->whatsapp,
            'text' => 'teste',
        ],
        'status' => 'queued',
        'idempotency_key' => 'outbox-empty-confirmation',
        'scheduled_at' => now(),
    ]);

    $this->mock(WhatsAppService::class, function ($mock): void {
        $mock->shouldReceive('sendTextViaInstance')->once()->andReturn('');
    });

    // A 2xx with no message id is an undecidable send: the message MAY have reached
    // Meta, so we must NOT blindly retry (no duplicate). The row is parked in_doubt for
    // a webhook / reconciliation to resolve, and never marked sent.
    app()->call([new ProcessWhatsappOutboxMessageJob($outbox->id), 'handle']);

    expect($outbox->fresh()->status)->toBe('in_doubt')
        ->and($outbox->fresh()->provider_message_id)->toBeNull();
});

test('outbox fails when payload is missing instance id', function (): void {
    $lead = Lead::factory()->create();

    $outbox = WhatsappOutboxMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'channel' => 'whatsapp',
        'provider' => 'meta_cloud',
        'payload_json' => [
            'type' => 'text',
            'instance_name' => 'main',
            'phone' => $lead->whatsapp,
            'text' => 'teste',
        ],
        'status' => 'queued',
        'idempotency_key' => 'outbox-missing-instance-id',
        'scheduled_at' => now(),
    ]);

    expect(fn () => app()->call([new ProcessWhatsappOutboxMessageJob($outbox->id), 'handle']))
        ->toThrow(RuntimeException::class, 'missing instance_id');

    expect($outbox->fresh()->status)->toBe('failed');
});

test('outbox rejects instance id from another tenant', function (): void {
    $instance = WhatsappInstance::factory()->create(['tenant_id' => 'tenant-a']);
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-b']);

    $outbox = WhatsappOutboxMessage::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'channel' => 'whatsapp',
        'provider' => 'meta_cloud',
        'payload_json' => [
            'type' => 'text',
            'instance_id' => $instance->id,
            'instance_name' => $instance->name,
            'phone' => $lead->whatsapp,
            'text' => 'teste',
        ],
        'status' => 'queued',
        'idempotency_key' => 'outbox-cross-tenant-instance',
        'scheduled_at' => now(),
    ]);

    expect(fn () => app()->call([new ProcessWhatsappOutboxMessageJob($outbox->id), 'handle']))
        ->toThrow(RuntimeException::class, 'does not belong to this tenant');

    expect($outbox->fresh()->status)->toBe('failed');
});

test('conversation screen receives WhatsApp conversation window status', function (): void {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'service_window_expires_at' => now()->addHours(2),
    ]);

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('activeConversation.conversationWindow.service_window.status', 'open')
            ->where('activeConversation.conversationWindow.template_required', false)
        );
});
