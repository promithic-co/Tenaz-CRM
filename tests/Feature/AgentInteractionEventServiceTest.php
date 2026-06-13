<?php

use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Models\Agent;
use App\Models\AgentInteractionEvent;
use App\Models\Lead;
use App\Services\AgentInteractionEventService;
use App\Services\AgentService;
use App\Services\ConversationAutomationService;
use App\Services\ConversationTimelineService;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\IncomingConversationPersister;
use App\Services\WhatsappOutboxService;
use Illuminate\Support\Facades\Event;

test('agent interaction event service creates traceable events', function () {
    $lead = Lead::factory()->create([
        'tenant_id' => 'tenant-1',
        'agent_id' => null,
    ]);

    $service = app(AgentInteractionEventService::class);
    $interactionId = $service->newInteractionId();

    $event = $service->recordForLead(
        interactionId: $interactionId,
        lead: $lead,
        eventType: 'inbound_received',
        eventSource: 'whatsapp_webhook',
        payload: ['provider_message_id' => 'wamid.test'],
    );

    expect($event)->toBeInstanceOf(AgentInteractionEvent::class)
        ->and($event->interaction_id)->toBe($interactionId)
        ->and($event->tenant_id)->toBe('tenant-1')
        ->and($event->lead_id)->toBe($lead->id)
        ->and($event->event_type)->toBe('inbound_received')
        ->and($event->payload_json)->toBe(['provider_message_id' => 'wamid.test']);
});

test('agent interaction event service can record tenant events before lead exists', function () {
    $service = app(AgentInteractionEventService::class);
    $interactionId = $service->newInteractionId();

    $event = $service->record(
        interactionId: $interactionId,
        tenantId: 'tenant-2',
        eventType: 'webhook_received',
        eventSource: 'meta_webhook',
        severity: 'debug',
    );

    expect($event->tenant_id)->toBe('tenant-2')
        ->and($event->lead_id)->toBeNull()
        ->and($event->payload_json)->toBeNull()
        ->and($event->severity)->toBe('debug');
});

test('incoming whatsapp job records inbound event and passes interaction id to agent service', function () {
    Event::fake();

    $agent = Agent::factory()->create([
        'tenant_id' => 'tenant-3',
        'is_active' => true,
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => 'tenant-3',
        'whatsapp' => '5511999990003',
        'agent_id' => $agent->id,
        'ai_mode' => Lead::AI_MODE_AUTOMATIC,
    ]);

    $interactionId = app(AgentInteractionEventService::class)->newInteractionId();

    $this->mock(AgentService::class, function ($mock) use ($interactionId, $lead): void {
        $mock->shouldReceive('process')
            ->once()
            ->withArgs(fn (Lead $passedLead, string $message, mixed $media, ?string $passedInteractionId): bool => $passedLead->is($lead)
                && $message === 'Quero simular uma consulta'
                && $media === null
                && $passedInteractionId === $interactionId)
            ->andReturn(null);
    });
    $this->mock(DashboardMetricsService::class, fn ($mock) => $mock->shouldReceive('dispatchUpdate'));

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: $lead->whatsapp,
        name: 'Cliente Trace',
        tenantId: 'tenant-3',
        agentId: $agent->id,
        instanceName: 'trace-instance',
        aggregatedMessage: 'Quero simular uma consulta',
        interactionId: $interactionId,
        providerMessageId: 'wamid.trace',
    );

    $job->handle(
        app(AgentService::class),
        app(WhatsappOutboxService::class),
        app(ConversationAutomationService::class),
        app(IncomingConversationPersister::class),
        app(ConversationTimelineService::class),
    );

    $event = AgentInteractionEvent::query()
        ->where('interaction_id', $interactionId)
        ->where('event_type', 'inbound_received')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->tenant_id)->toBe('tenant-3')
        ->and($event->lead_id)->toBe($lead->id)
        ->and($event->payload_json['provider_message_id'])->toBe('wamid.trace')
        ->and($event->payload_json['instance_name'])->toBe('trace-instance');
});

test('laboratory exposes interaction timeline scoped by tenant', function () {
    $user = userWithTenant();
    $this->actingAs($user);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => null,
    ]);

    $service = app(AgentInteractionEventService::class);
    $interactionId = $service->newInteractionId();
    $service->recordForLead(
        interactionId: $interactionId,
        lead: $lead,
        eventType: 'agent_started',
        eventSource: 'test',
        payload: ['ok' => true],
    );

    $this->getJson(route('laboratory.interactions.show', ['interactionId' => $interactionId]))
        ->assertOk()
        ->assertJsonPath('interaction_id', $interactionId)
        ->assertJsonPath('events.0.event_type', 'agent_started')
        ->assertJsonPath('events.0.payload.ok', true);
});
