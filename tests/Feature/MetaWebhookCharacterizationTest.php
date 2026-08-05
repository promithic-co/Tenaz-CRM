<?php

use App\Jobs\AggregateDebouncedMessageJob;
use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Models\Agent;
use App\Models\AgentInteractionEvent;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Services\AgentInteractionEventService;
use App\Services\AgentService;
use App\Services\ConversationAutomationService;
use App\Services\DebounceService;
use App\Services\IncomingConversationPersister;
use App\Services\WhatsappOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * CHARACTERIZATION TEST for canonical path 1 — the Meta Cloud inbound webhook
 * (Phase 61 / RUNT-01, success criterion 5 in 61-VALIDATION.md).
 *
 * These assertions pin the CURRENT, UNDESIRABLE behaviour of UNMODIFIED production code as
 * a receipt for Phase 62. Today there is no ownership check between AgentService::process()
 * returning and WhatsappOutboxService queueing the answer, so a turn superseded by a newer
 * customer message is queued and sent anyway, and no supersession evidence is recorded
 * anywhere. That is a defect. This file documents it; it does not endorse it.
 *
 * A FUTURE READER MUST NOT "FIX" THESE ASSERTIONS TO DESCRIBE DESIRED BEHAVIOUR. When Phase 62
 * introduces current-execution ownership this file is EXPECTED to fail, and it must then be
 * rewritten deliberately as part of that change — never silently adjusted, which would erase
 * the before/after this phase exists to create.
 *
 * Fixture-accuracy note: the two turns run as two sequential ->handle() calls, which is not an
 * approximation — WithoutOverlapping("incoming_msg_{tenant}_{phone}") makes that exactly what
 * production does. What is modelled synthetically is message B's ARRIVAL during turn A's model
 * call: the mock records B's webhook_received event and advances last_inbound_at, standing in
 * for AggregateDebouncedMessageJob (which carries no overlap guard and genuinely runs mid-turn)
 * and for the newest-known conversation state a latest-state check would consult. B's inbound
 * timeline row is deliberately NOT inserted mid-turn, because the overlap lock provably prevents
 * that interleaving within one attempt.
 *
 * @see .engineering/runtime-characterization/path-1-meta-webhook.md
 */

/** Obviously-fake customer number: never a real subscriber, per D-29. */
const META_WEBHOOK_CHARACTERIZATION_PHONE = '5500000000001';

/**
 * Tenant + active agent + Meta Cloud instance + an automation-eligible lead.
 *
 * @return array{Agent, WhatsappInstance, Lead}
 */
function metaWebhookCharacterizationLead(): array
{
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenantId,
        'is_active' => true,
    ]);

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenantId,
        'agent_id' => $agent->id,
        'name' => 'char-path1-instance',
        'default_ai_mode' => Lead::AI_MODE_AUTOMATIC,
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'agent_id' => $agent->id,
        'whatsapp' => META_WEBHOOK_CHARACTERIZATION_PHONE,
        'whatsapp_instance_id' => $instance->id,
        'ai_mode' => Lead::AI_MODE_AUTOMATIC,
    ]);

    return [$agent, $instance, $lead];
}

/** Build an inbound job exactly as AggregateDebouncedMessageJob dispatches it. */
function metaWebhookCharacterizationJob(
    Lead $lead,
    WhatsappInstance $instance,
    string $message,
    string $interactionId,
    string $providerMessageId,
): ProcessIncomingWhatsAppMessageJob {
    return new ProcessIncomingWhatsAppMessageJob(
        phone: (string) $lead->whatsapp,
        name: 'Cliente Caracterizacao',
        tenantId: (string) $lead->tenant_id,
        agentId: $lead->agent_id,
        instanceName: $instance->name,
        aggregatedMessage: $message,
        mediaContext: null,
        interactionId: $interactionId,
        providerMessageId: $providerMessageId,
    );
}

/** Run the job with its real injected dependencies resolved from the container. */
function runMetaWebhookCharacterizationJob(ProcessIncomingWhatsAppMessageJob $job): void
{
    $job->handle(
        app(AgentService::class),
        app(WhatsappOutboxService::class),
        app(ConversationAutomationService::class),
        app(IncomingConversationPersister::class),
    );
}

/**
 * Drive the path 1 collision: turn A's model call is in flight when message B arrives,
 * then turn B runs. Returns the lead, how many outbox rows existed for it at the exact
 * instant message B arrived, and both interaction ids.
 *
 * @return array{Lead, int, string, string}
 */
function runMetaWebhookCharacterizationCollision(string $answerA, string $answerB): array
{
    [, $instance, $lead] = metaWebhookCharacterizationLead();

    $interactionA = 'char-path1-interaction-a';
    $interactionB = 'char-path1-interaction-b';

    $outboxRowsWhenMessageBArrived = -1;
    $turn = 0;

    $agentService = Mockery::mock(AgentService::class);
    $agentService->shouldReceive('process')
        ->twice()
        ->andReturnUsing(function () use (
            $lead,
            $interactionB,
            $answerA,
            $answerB,
            &$outboxRowsWhenMessageBArrived,
            &$turn,
        ): string {
            $turn++;

            if ($turn > 1) {
                return $answerB;
            }

            // Turn A is inside the model call. Nothing has been queued yet.
            $outboxRowsWhenMessageBArrived = WhatsappOutboxMessage::query()
                ->where('lead_id', $lead->id)
                ->count();

            // Message B arrives. AggregateDebouncedMessageJob has no overlap guard, so it
            // genuinely drains B's window and records this event while turn A is still running.
            app(AgentInteractionEventService::class)->record(
                interactionId: $interactionB,
                tenantId: (string) $lead->tenant_id,
                eventType: 'webhook_received',
                eventSource: 'meta_webhook_controller',
                payload: ['channel' => 'whatsapp', 'has_media' => false],
                leadId: $lead->id,
            );

            // The newest-known conversation state now includes message B — the exact signal a
            // latest-state ownership check would consult. Nothing on this path consults it.
            $lead->forceFill(['last_inbound_at' => now()->addSeconds(2)])->saveQuietly();

            return $answerA;
        });

    app()->instance(AgentService::class, $agentService);

    runMetaWebhookCharacterizationJob(
        metaWebhookCharacterizationJob($lead, $instance, 'Mensagem A', $interactionA, 'wamid.CHAR-A'),
    );

    runMetaWebhookCharacterizationJob(
        metaWebhookCharacterizationJob($lead, $instance, 'Mensagem B', $interactionB, 'wamid.CHAR-B'),
    );

    return [$lead, $outboxRowsWhenMessageBArrived, $interactionA, $interactionB];
}

// ---------------------------------------------------------------------------
// 1. Entry contract — the happy path into the collection window
// ---------------------------------------------------------------------------

test('characterization: a webhook text message schedules one aggregation job carrying one minted interaction id', function () {
    Queue::fake();
    config()->set('services.meta.app_secret', 'char-path1-secret');
    config()->set('credflow.debounce_seconds', 3);

    // Redis is not available in the test environment; DebounceService is the buffer, not the
    // subject here — pin only that a non-quick message is buffered and the delayed drain scheduled.
    $this->mock(DebounceService::class, function ($mock): void {
        $mock->shouldReceive('isQuickCommand')->andReturn(false);
        $mock->shouldReceive('push')->andReturn(true);
    });

    [, $instance] = metaWebhookCharacterizationLead();

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => $instance->meta_waba_id,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['phone_number_id' => $instance->meta_phone_number_id],
                    'contacts' => [['profile' => ['name' => 'Cliente Caracterizacao'], 'wa_id' => META_WEBHOOK_CHARACTERIZATION_PHONE]],
                    'messages' => [[
                        'from' => META_WEBHOOK_CHARACTERIZATION_PHONE,
                        'id' => 'wamid.CHAR-ENTRY',
                        'timestamp' => (string) time(),
                        'type' => 'text',
                        'text' => ['body' => 'Quero entender as condicoes'],
                    ]],
                ],
            ]],
        ]],
    ];

    $body = json_encode($payload);

    $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'char-path1-secret'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    )->assertNoContent();

    // The HTTP request itself does no conversational work: it buffers and returns.
    Queue::assertNotPushed(ProcessIncomingWhatsAppMessageJob::class);
    Queue::assertPushed(AggregateDebouncedMessageJob::class, 1);
    Queue::assertPushed(
        AggregateDebouncedMessageJob::class,
        fn (AggregateDebouncedMessageJob $job): bool => $job->phone === META_WEBHOOK_CHARACTERIZATION_PHONE
            && $job->tenantId === (string) $instance->tenant_id
            && $job->instanceName === $instance->name
            && $job->providerMessageId === 'wamid.CHAR-ENTRY'
            && Str::isUuid((string) $job->interactionId),
    );
});

// ---------------------------------------------------------------------------
// 2. The collision receipt — success criterion 5
// ---------------------------------------------------------------------------

test('characterization: turn A answer is still queued after message B arrives mid-turn, producing two outbox rows', function () {
    Queue::fake();

    [$lead, $outboxRowsWhenMessageBArrived, $interactionA, $interactionB] =
        runMetaWebhookCharacterizationCollision('Resposta do turno A', 'Resposta do turno B');

    // Turn A had queued nothing yet when message B arrived — its answer was still, in principle,
    // stoppable. Nothing stopped it.
    expect($outboxRowsWhenMessageBArrived)->toBe(0);

    // Message B's arrival was on record before turn A's answer was queued.
    expect(
        AgentInteractionEvent::query()
            ->where('interaction_id', $interactionB)
            ->where('event_type', 'webhook_received')
            ->exists(),
    )->toBeTrue();

    $rows = WhatsappOutboxMessage::query()
        ->where('lead_id', $lead->id)
        ->orderBy('id')
        ->get();

    // THE RECEIPT: two answers for one conversation. The first was written with no knowledge
    // of message B and is queued anyway.
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('interaction_id')->all())->toBe([$interactionA, $interactionB]);
    expect($rows->first()->payload_json['text'])->toBe('Resposta do turno A');
    expect($rows->last()->payload_json['text'])->toBe('Resposta do turno B');

    // Both rows are outbound-pending: neither the stale one nor the fresh one is distinguished.
    expect($rows->pluck('status')->unique()->values()->all())->toBe(['queued']);

    // Tenant attribution survives as a string on every row (queue context has no auth scope).
    expect($rows->pluck('tenant_id')->unique()->values()->all())->toBe([(string) $lead->tenant_id]);
    expect($rows->first()->tenant_id)->toBeString();

    // The conversation state had already moved past turn A when its answer was queued.
    expect($lead->fresh()->last_inbound_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// 3. Negative evidence — no supersession concept exists anywhere
// ---------------------------------------------------------------------------

test('characterization: the collision produces no supersession evidence of any kind', function () {
    Queue::fake();

    runMetaWebhookCharacterizationCollision('Resposta do turno A', 'Resposta do turno B');

    // Neither event type exists in the codebase. This is the single most important fact
    // Phase 62 inherits: there is nothing to extend, only something to build.
    expect(
        AgentInteractionEvent::query()->where('event_type', 'execution_superseded')->doesntExist(),
    )->toBeTrue();

    expect(
        AgentInteractionEvent::query()->where('event_type', 'response_blocked_stale')->doesntExist(),
    )->toBeTrue();

    // The positive trail is recorded in full, which is precisely why the absence is legible:
    // the path knows how to say "queued", never "blocked as obsolete".
    expect(
        AgentInteractionEvent::query()->where('event_type', 'outbound_queued')->exists(),
    )->toBeTrue();
});

// ---------------------------------------------------------------------------
// 4. Split responses — one row per part, none cancellable
// ---------------------------------------------------------------------------

test('characterization: a split response queues one uncancellable row per part', function () {
    Queue::fake();

    [$lead, , $interactionA, $interactionB] = runMetaWebhookCharacterizationCollision(
        "Primeira parte do turno A.\n\nSegunda parte do turno A.",
        'Resposta do turno B',
    );

    $rows = WhatsappOutboxMessage::query()
        ->where('lead_id', $lead->id)
        ->orderBy('id')
        ->get();

    // Two parts for turn A plus one for turn B.
    expect($rows)->toHaveCount(3);
    expect($rows->where('interaction_id', $interactionA))->toHaveCount(2);
    expect($rows->where('interaction_id', $interactionB))->toHaveCount(1);

    // Parts are independently scheduled, two seconds apart, with no group identity binding them.
    $turnAParts = $rows->where('interaction_id', $interactionA)->values();
    expect($turnAParts->first()->payload_json['text'])->toBe('Primeira parte do turno A.');
    expect($turnAParts->last()->payload_json['text'])->toBe('Segunda parte do turno A.');
    expect($turnAParts->first()->idempotency_key)->not->toBe($turnAParts->last()->idempotency_key);

    // NOTHING CANCELS THEM. Every row — including turn A's superseded second part — is still in
    // a pre-send status after the collision, and ProcessWhatsappOutboxMessageJob will send each
    // one on schedule. There is no cancelled/superseded status for a row to move to.
    expect($rows->pluck('status')->unique()->values()->all())->toBe(['queued']);
    expect($rows->whereNotNull('provider_attempted_at'))->toHaveCount(0);
    expect($rows->whereNotNull('sent_at'))->toHaveCount(0);
});
