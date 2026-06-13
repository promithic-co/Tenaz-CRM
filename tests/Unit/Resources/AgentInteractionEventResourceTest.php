<?php

use App\Http\Resources\AgentInteractionEventResource;
use App\Models\AgentInteractionEvent;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Parity baseline for the `recentEvents` columns selected in
 * ConversasController::conversationProps — the legacy code passed the raw model
 * (event_type / created_at / severity / payload_json) straight to Inertia.
 */
function legacyEventShape(AgentInteractionEvent $event): array
{
    return [
        'event_type' => $event->event_type,
        'created_at' => $event->created_at,
        'severity' => $event->severity,
        'payload_json' => $event->payload_json,
    ];
}

it('matches the legacy recentEvents column shape', function () {
    $lead = Lead::factory()->create();

    $event = AgentInteractionEvent::create([
        'interaction_id' => (string) Str::uuid(),
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'event_type' => 'ai_paused_manual',
        'event_source' => 'operator',
        'severity' => 'warning',
        'payload_json' => ['reason' => 'manual override', 'by' => 'agent'],
    ]);

    $event = $event->fresh(); // re-read so created_at is the DB datetime cast

    $resource = (new AgentInteractionEventResource($event))->toArray(request());

    expect($resource)->toEqual(legacyEventShape($event));
    expect(array_keys($resource))->toBe(['event_type', 'created_at', 'severity', 'payload_json']);
    expect($resource['payload_json'])->toBe(['reason' => 'manual override', 'by' => 'agent']);
    expect($resource['created_at'])->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

it('preserves a null payload', function () {
    $lead = Lead::factory()->create();

    $event = AgentInteractionEvent::create([
        'interaction_id' => (string) Str::uuid(),
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'event_type' => 'history_cleared_manual',
        'event_source' => 'operator',
        'severity' => 'info',
        'payload_json' => null,
    ])->fresh();

    $resource = (new AgentInteractionEventResource($event))->toArray(request());

    expect($resource)->toEqual(legacyEventShape($event));
    expect($resource['payload_json'])->toBeNull();
    expect($resource['severity'])->toBe('info');
});
