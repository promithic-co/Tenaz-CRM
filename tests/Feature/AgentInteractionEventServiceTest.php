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

test('buffered events are withheld until flush then bulk inserted in order (SCALE-7)', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-7', 'agent_id' => null]);

    $service = app(AgentInteractionEventService::class);
    $interactionId = $service->newInteractionId();

    $service->bufferForLead($interactionId, $lead, 'agent_started', 'agent_service', ['n' => 1]);
    $service->bufferForLead($interactionId, $lead, 'agent_response_ready', 'agent_service', ['n' => 2]);

    // Nothing is written to the DB while events sit in the buffer.
    expect(AgentInteractionEvent::where('interaction_id', $interactionId)->count())->toBe(0);

    $written = $service->flush();

    $events = AgentInteractionEvent::where('interaction_id', $interactionId)->orderBy('id')->get();

    expect($written)->toBe(2)
        ->and($events)->toHaveCount(2)
        ->and($events[0]->event_type)->toBe('agent_started')
        ->and($events[0]->payload_json)->toBe(['n' => 1])
        ->and($events[0]->tenant_id)->toBe('tenant-7')
        ->and($events[0]->lead_id)->toBe($lead->id)
        ->and($events[0]->created_at)->not->toBeNull()
        ->and($events[1]->event_type)->toBe('agent_response_ready')
        ->and($events[1]->payload_json)->toBe(['n' => 2]);

    // Buffer is emptied after flush; a second flush is a no-op.
    expect($service->flush())->toBe(0)
        ->and(AgentInteractionEvent::where('interaction_id', $interactionId)->count())->toBe(2);
});

test('buffered event with empty payload stores null payload_json (SCALE-7)', function () {
    $lead = Lead::factory()->create(['tenant_id' => 'tenant-7b', 'agent_id' => null]);

    $service = app(AgentInteractionEventService::class);
    $interactionId = $service->newInteractionId();

    $service->bufferForLead($interactionId, $lead, 'fact_check_passed', 'agent_service');
    $service->flush();

    $event = AgentInteractionEvent::where('interaction_id', $interactionId)->first();

    expect($event->payload_json)->toBeNull()
        ->and($event->severity)->toBe('info');
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

test('lead interaction timeline caps results at the most recent slice and flags truncation (MEM-2)', function () {
    $user = userWithTenant();
    $this->actingAs($user);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => null,
    ]);

    $total = 503;
    $base = now()->subMinutes($total);
    $rows = [];
    for ($seq = 0; $seq < $total; $seq++) {
        $rows[] = [
            'interaction_id' => 'int-'.$seq,
            'tenant_id' => (string) $lead->tenant_id,
            'lead_id' => $lead->id,
            'agent_id' => null,
            'event_type' => 'agent_step',
            'event_source' => 'test',
            'severity' => 'info',
            'payload_json' => json_encode(['seq' => $seq]),
            'created_at' => $base->copy()->addSeconds($seq)->format('Y-m-d H:i:s'),
        ];
    }
    collect($rows)->chunk(100)->each(fn ($chunk) => AgentInteractionEvent::insert($chunk->all()));

    $this->getJson(route('laboratory.leads.interactions', ['lead' => $lead->id]))
        ->assertOk()
        ->assertJsonPath('lead_id', $lead->id)
        ->assertJsonPath('total_events', $total)
        ->assertJsonPath('returned_events', 500)
        ->assertJsonPath('truncated', true)
        // Most recent 500 are returned oldest-first: seq 3 leads, seq 502 trails.
        ->assertJsonPath('events.0.payload.seq', 3)
        ->assertJsonPath('events.499.payload.seq', 502);
});

test('lead interaction timeline returns all events chronologically under the cap and honors limit (MEM-2)', function () {
    $user = userWithTenant();
    $this->actingAs($user);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => null,
    ]);

    $service = app(AgentInteractionEventService::class);
    foreach (['a', 'b', 'c'] as $i => $type) {
        $service->recordForLead(
            interactionId: 'int-'.$i,
            lead: $lead,
            eventType: $type,
            eventSource: 'test',
        );
    }

    $this->getJson(route('laboratory.leads.interactions', ['lead' => $lead->id]))
        ->assertOk()
        ->assertJsonPath('total_events', 3)
        ->assertJsonPath('returned_events', 3)
        ->assertJsonPath('truncated', false)
        ->assertJsonPath('events.0.event_type', 'a')
        ->assertJsonPath('events.2.event_type', 'c');

    $this->getJson(route('laboratory.leads.interactions', ['lead' => $lead->id, 'limit' => 2]))
        ->assertOk()
        ->assertJsonPath('total_events', 3)
        ->assertJsonPath('returned_events', 2)
        ->assertJsonPath('truncated', true)
        ->assertJsonPath('events.0.event_type', 'b')
        ->assertJsonPath('events.1.event_type', 'c');
});
