<?php

use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Verbatim replica of the AgentsController::index mapper — the parity baseline.
 * The aggregates are computed the legacy way (per-agent leads query) so the
 * test proves the resource emits the identical flat shape once the F.3
 * withCount aggregates are fed onto the model.
 */
function legacyAgentShape(Agent $agent): array
{
    $leads = $agent->leads()->production()->get(['status']);
    $total = $leads->count();
    $active = $leads->whereIn('status', ['novo', 'qualificado', 'escalado'])->count();
    $converted = $leads->where('status', 'convertido')->count();
    $agentNiche = $agent->config?->agent_niche ?? 'inss';
    $specialization = config("credflow.agent_specializations.{$agentNiche}");

    return [
        'id' => $agent->id,
        'name' => $agent->name,
        'description' => $agent->description,
        'is_active' => $agent->is_active,
        'is_default' => $agent->is_default,
        'display_agent_name' => $agent->config?->agent_name,
        'model' => $agent->config?->agent_model,
        'provider' => $agent->config?->agent_provider,
        'template_slug' => $agent->config?->template_slug,
        'agent_niche' => $agentNiche,
        'specialization' => [
            'value' => $agentNiche,
            'label' => $specialization['label'],
            'description' => $specialization['description'],
            'badge_classes' => $specialization['badge_classes'],
        ],
        'instance' => $agent->whatsappInstance ? [
            'id' => $agent->whatsappInstance->id,
            'name' => $agent->whatsappInstance->name,
            'display_name' => $agent->whatsappInstance->display_name,
            'phone_number' => $agent->whatsappInstance->phone_number,
        ] : null,
        'leads_count' => $total,
        'active_conversations' => $active,
        'converted_count' => $converted,
        'conversion_rate' => $total > 0 ? round(($converted / $total) * 100) : 0,
    ];
}

it('matches the legacy index mapper output with aggregates fed via attributes', function () {
    $agent = Agent::factory()->create(['description' => 'Vendedor INSS']);
    AgentConfig::factory()->create(['agent_id' => $agent->id, 'tenant_id' => $agent->tenant_id]);
    $instance = WhatsappInstance::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $agent->tenant_id,
    ]);
    $agent->setRelation('whatsappInstance', $instance);

    foreach (['novo', 'qualificado', 'escalado', 'convertido', 'convertido', 'perdido'] as $status) {
        Lead::factory()->forAgent($agent)->create(['status' => $status]);
    }

    $agent->load('config');
    $expected = legacyAgentShape($agent);

    $agent->setAttribute('leads_count', $expected['leads_count']);
    $agent->setAttribute('active_conversations', $expected['active_conversations']);
    $agent->setAttribute('converted_count', $expected['converted_count']);

    $resource = (new AgentResource($agent))->toArray(request());

    expect($resource)->toEqual($expected);
    expect($resource['leads_count'])->toBe(6);
    expect($resource['active_conversations'])->toBe(3);
    expect($resource['converted_count'])->toBe(2);
    expect($resource['conversion_rate'])->toBe(round((2 / 6) * 100));
});

it('emits zero aggregates and a null instance when none are provided', function () {
    $agent = Agent::factory()->create(['description' => null]);
    AgentConfig::factory()->create(['agent_id' => $agent->id, 'tenant_id' => $agent->tenant_id]);
    $agent->load('config');
    $agent->setRelation('whatsappInstance', null);

    $resource = (new AgentResource($agent))->toArray(request());

    expect($resource)->toEqual(legacyAgentShape($agent));
    expect($resource['instance'])->toBeNull();
    expect($resource['leads_count'])->toBe(0);
    expect($resource['conversion_rate'])->toBe(0);
});

it('reads aggregates from additional() metadata when model attributes are absent', function () {
    $agent = Agent::factory()->create();
    AgentConfig::factory()->create(['agent_id' => $agent->id, 'tenant_id' => $agent->tenant_id]);
    $agent->load('config');
    $agent->setRelation('whatsappInstance', null);

    $resource = (new AgentResource($agent))
        ->additional([
            'leads_count' => 10,
            'active_conversations' => 4,
            'converted_count' => 5,
            'conversion_rate' => 50,
        ])
        ->toArray(request());

    expect($resource['leads_count'])->toBe(10);
    expect($resource['active_conversations'])->toBe(4);
    expect($resource['converted_count'])->toBe(5);
    expect($resource['conversion_rate'])->toBe(50);
});
