<?php

use App\Http\Resources\ConversationResource;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Verbatim replica of the $leadData block in
 * ConversasController::conversationProps (lead sub-object only) — the parity
 * baseline. The two service-derived fields are injected, matching the resource
 * contract.
 *
 * @param  array<int, string>  $availableTransitions
 */
function legacyLeadData(Lead $lead, array $availableTransitions, string $effectiveAiMode): array
{
    return [
        'id' => $lead->id,
        'agent_id' => $lead->agent_id,
        'contact_id' => $lead->contact_id,
        'nome' => $lead->nome ?? $lead->whatsapp,
        'whatsapp' => $lead->whatsapp,
        'cpf' => $lead->cpf,
        'idade' => $lead->idade,
        'status' => $lead->status,
        'available_transitions' => $availableTransitions,
        'ai_mode' => $lead->ai_mode,
        'effective_ai_mode' => $effectiveAiMode,
        'operational_stage' => $lead->operational_stage,
        'assigned_user_id' => $lead->assigned_user_id,
        'assigned_user_name' => $lead->assignedUser?->name,
        'ai_paused_until' => $lead->ai_paused_until?->toIso8601String(),
        'ai_paused_reason' => $lead->ai_paused_reason,
        'followup_count' => $lead->followup_count,
        'followup_status' => $lead->followup_status,
        'resumo_credito' => $lead->credito_json['resumoGeral']['textoResumo'] ?? null,
        'tags' => $lead->tags->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'color' => $t->color,
            'source' => $t->pivot?->source,
            'ai_confidence' => $t->pivot?->ai_confidence !== null ? (float) $t->pivot->ai_confidence : null,
        ])->all(),
    ];
}

it('matches the legacy $leadData lead sub-object', function () {
    $agent = Agent::factory()->create();
    $instance = WhatsappInstance::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $agent->tenant_id,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'nome' => 'Ana Paula',
        'whatsapp' => '5511966665555',
        'cpf' => '12345678901',
        'idade' => 47,
        'status' => 'qualificado',
        'ai_mode' => 'automatic',
        'operational_stage' => 'engaging',
        'followup_count' => 2,
        'followup_status' => 'active',
        'whatsapp_instance_id' => $instance->id,
        'credito_json' => ['resumoGeral' => ['textoResumo' => 'Margem disponível R$ 1.200']],
    ]);

    $tag = Tag::factory()->forTenant($lead->tenant_id)->create();
    $lead->tags()->attach($tag->id, [
        'source' => 'ai',
        'ai_confidence' => 0.82,
        'ai_evidence' => 'mentioned loan',
        'ai_evaluated_at' => now(),
    ]);

    $lead->load([
        'whatsappInstance',
        'tags' => fn ($q) => $q->withPivot('source', 'ai_confidence', 'ai_evidence', 'ai_evaluated_at'),
    ]);

    $transitions = ['qualificado', 'escalado', 'convertido'];
    $effectiveAiMode = 'automatic';

    $resource = (new ConversationResource($lead, $transitions, $effectiveAiMode))
        ->toArray(request());

    expect($resource)->toEqual(legacyLeadData($lead, $transitions, $effectiveAiMode));
    expect($resource)->toHaveKey('cpf');
    expect($resource['cpf'])->toBe('12345678901');
    expect($resource['resumo_credito'])->toBe('Margem disponível R$ 1.200');
    expect($resource['tags'][0]['source'])->toBe('ai');
    expect($resource['tags'][0]['ai_confidence'])->toBe(0.82);
    expect($resource['tags'][0])->not->toHaveKey('ai_evidence');
    expect($resource['tags'][0])->not->toHaveKey('ai_evaluated_at');
});

it('falls back to whatsapp for nome and emits null credit summary when absent', function () {
    $lead = Lead::factory()->create([
        'nome' => null,
        'whatsapp' => '5511955554444',
        'cpf' => null,
        'credito_json' => null,
    ]);

    $lead->load([
        'whatsappInstance',
        'tags' => fn ($q) => $q->withPivot('source', 'ai_confidence', 'ai_evidence', 'ai_evaluated_at'),
    ]);

    $resource = (new ConversationResource($lead, [], 'manual'))->toArray(request());

    expect($resource)->toEqual(legacyLeadData($lead, [], 'manual'));
    expect($resource['nome'])->toBe('5511955554444');
    expect($resource['resumo_credito'])->toBeNull();
    expect($resource['available_transitions'])->toBe([]);
    expect($resource['tags'])->toBe([]);
});
