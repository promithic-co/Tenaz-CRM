<?php

use App\Http\Resources\LeadCardResource;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Verbatim replica of PipelineController::toCardShape — the parity baseline.
 * The two injected values (automation_state, source_label) are computed here
 * the same way the controller does, then handed to the resource.
 *
 * Deliberately frozen at the legacy shape: fields added after the port
 * (`value_cents`) are layered on by the individual tests, so this stays a
 * record of what the controller emitted rather than a copy of the resource.
 */
function legacyCardShape(Lead $lead, string $automationState, string $sourceLabel): array
{
    return [
        'id' => $lead->id,
        'contact_id' => $lead->contact_id,
        'nome' => $lead->nome,
        'whatsapp' => $lead->whatsapp,
        'status' => $lead->status,
        'automation_state' => $automationState,
        'followup_status' => $lead->followup_status,
        'source_label' => $sourceLabel,
        'last_message' => mb_substr((string) ($lead->last_message_preview ?? ''), 0, 60),
        'last_interaction_at' => $lead->last_interaction_at?->toIso8601String(),
        'sla_due_at' => $lead->sla_due_at?->toIso8601String() ?? null,
        'tags' => $lead->tags?->map(fn ($tag): array => [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'color' => $tag->color,
            'is_hot' => (bool) ($tag->is_hot ?? false),
        ])->values()->all(),
    ];
}

it('matches the legacy toCardShape output for a tagged lead', function () {
    $agent = Agent::factory()->create();
    $lead = Lead::factory()->forAgent($agent)->create([
        'nome' => 'Maria Souza',
        'whatsapp' => '5511988887777',
        'status' => 'qualificado',
        'followup_status' => 'active',
        'last_interaction_at' => now()->subHour(),
    ]);

    // last_message_preview / sla_due_at are not persisted columns on `leads`; the
    // pipeline reads them as transient model attributes, so set them in-memory to
    // exercise the truncation + ISO-8601 branches exactly as the controller does.
    $lead->setAttribute('last_message_preview', str_repeat('a', 80));
    $lead->setAttribute('sla_due_at', now()->addHour());

    $hotTag = Tag::factory()->forTenant($lead->tenant_id)->hot()->create();
    $lead->tags()->attach($hotTag->id, ['source' => 'manual']);
    $lead->load('tags');

    $automationState = 'active';
    $sourceLabel = 'Receptivo';

    $resource = (new LeadCardResource($lead, $automationState, $sourceLabel))
        ->toArray(request());

    // value_cents is guarded on relationLoaded('openSession'): unloaded means null
    // rather than a lazy query, so the board never N+1s over the column.
    expect($resource)->toEqual(legacyCardShape($lead, $automationState, $sourceLabel) + ['value_cents' => null]);
    expect($resource)->toHaveKey('whatsapp');
    expect($resource)->not->toHaveKey('cpf');
    expect($resource['tags'][0]['is_hot'])->toBeTrue();
});

it('matches the legacy output for a lead with no tags and empty preview', function () {
    $lead = Lead::factory()->create([
        'last_interaction_at' => null,
    ]);
    $lead->load('tags');

    $resource = (new LeadCardResource($lead, 'manual', 'Sem origem'))
        ->toArray(request());

    expect($resource)->toEqual(legacyCardShape($lead, 'manual', 'Sem origem') + ['value_cents' => null]);
    expect($resource['last_message'])->toBe('');
    expect($resource['tags'])->toBe([]);
    expect($resource['last_interaction_at'])->toBeNull();
});
