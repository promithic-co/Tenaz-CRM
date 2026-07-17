<?php

use App\Ai\AgentFactory;
use App\Ai\Agents\CltAgent;
use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\CredFlowBulkAgent;
use App\Ai\Agents\CredFlowFollowUpAgent;
use App\Ai\Agents\GenericAgent;
use App\Ai\Agents\GenericFollowUpAgent;
use App\Ai\Agents\SiapeAgent;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Lead;
use App\Models\NicheTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('factory resolves CredFlowAgent for inss niche with receptivo modo', function () {
    $agent = Agent::factory()->has(AgentConfig::factory(), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'modo' => 'receptivo']);

    $resolved = app(AgentFactory::class)->make($lead);

    expect($resolved)->toBeInstanceOf(CredFlowAgent::class);
});

test('factory resolves SiapeAgent for siape niche with receptivo modo', function () {
    $agent = Agent::factory()->has(AgentConfig::factory()->siape(), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'modo' => 'receptivo']);

    $resolved = app(AgentFactory::class)->make($lead);

    expect($resolved)->toBeInstanceOf(SiapeAgent::class);
});

test('factory resolves CltAgent for clt niche with receptivo modo', function () {
    $agent = Agent::factory()->has(AgentConfig::factory()->clt(), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'modo' => 'receptivo']);

    $resolved = app(AgentFactory::class)->make($lead);

    expect($resolved)->toBeInstanceOf(CltAgent::class);
});

test('factory resolves CredFlowBulkAgent for inss niche with bulk modo', function () {
    $agent = Agent::factory()->has(AgentConfig::factory(), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'modo' => 'bulk']);

    $resolved = app(AgentFactory::class)->make($lead);

    expect($resolved)->toBeInstanceOf(CredFlowBulkAgent::class);
});

test('factory resolves GenericAgent when niche is unknown to the legacy map', function () {
    $agent = Agent::factory()->has(AgentConfig::factory()->state(['agent_niche' => 'unknown']), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'modo' => 'receptivo']);

    $resolved = app(AgentFactory::class)->make($lead);

    expect($resolved)->toBeInstanceOf(GenericAgent::class);
});

test('factory resolves GenericAgent for the generic niche', function () {
    $agent = Agent::factory()->has(AgentConfig::factory()->state(['agent_niche' => 'generic']), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'modo' => 'receptivo']);

    $resolved = app(AgentFactory::class)->make($lead);

    expect($resolved)->toBeInstanceOf(GenericAgent::class);
});

test('template agent_class overrides the legacy niche map', function () {
    NicheTemplate::factory()->create([
        'slug' => 'clinica-recepcao',
        'agent_class' => SiapeAgent::class,
    ]);

    $agent = Agent::factory()->has(
        AgentConfig::factory()->state(['agent_niche' => 'inss', 'template_slug' => 'clinica-recepcao']),
        'config'
    )->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'modo' => 'receptivo']);

    $resolved = app(AgentFactory::class)->make($lead);

    expect($resolved)->toBeInstanceOf(SiapeAgent::class);
});

test('invalid template agent_class falls through to the niche map', function () {
    NicheTemplate::factory()->create([
        'slug' => 'clinica-recepcao',
        'agent_class' => 'App\\Ai\\Agents\\ClasseQueNaoExiste',
    ]);

    $agent = Agent::factory()->has(
        AgentConfig::factory()->state(['agent_niche' => 'inss', 'template_slug' => 'clinica-recepcao']),
        'config'
    )->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'modo' => 'receptivo']);

    $resolved = app(AgentFactory::class)->make($lead);

    expect($resolved)->toBeInstanceOf(CredFlowAgent::class);
});

test('factory defaults to inss when lead has no agent config', function () {
    $lead = Lead::factory()->create(['agent_id' => null, 'modo' => 'receptivo']);

    $resolved = app(AgentFactory::class)->make($lead);

    expect($resolved)->toBeInstanceOf(CredFlowAgent::class);
});

test('siape niche falls back to receptivo when modo not registered', function () {
    $agent = Agent::factory()->has(AgentConfig::factory()->siape(), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id, 'modo' => 'bulk']);

    $resolved = app(AgentFactory::class)->make($lead);

    // SIAPE has no 'bulk' registered, falls back to siape.receptivo
    expect($resolved)->toBeInstanceOf(SiapeAgent::class);
});

// --- Follow-up resolution (Slice 5) ---

test('makeFollowUp resolves CredFlowFollowUpAgent for the inss niche', function () {
    $agent = Agent::factory()->has(AgentConfig::factory(), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);

    $resolved = app(AgentFactory::class)->makeFollowUp($lead);

    expect($resolved)->toBeInstanceOf(CredFlowFollowUpAgent::class);
});

test('makeFollowUp resolves GenericFollowUpAgent for the generic niche', function () {
    $agent = Agent::factory()->has(AgentConfig::factory()->state(['agent_niche' => 'generic']), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);

    $resolved = app(AgentFactory::class)->makeFollowUp($lead);

    expect($resolved)->toBeInstanceOf(GenericFollowUpAgent::class);
});

test('makeFollowUp keeps the historical INSS follow-up for legacy niches without a followup entry', function () {
    $agent = Agent::factory()->has(AgentConfig::factory()->siape(), 'config')->create();
    $lead = Lead::factory()->create(['agent_id' => $agent->id]);

    $resolved = app(AgentFactory::class)->makeFollowUp($lead);

    expect($resolved)->toBeInstanceOf(CredFlowFollowUpAgent::class);
});

test('makeFollowUp defaults to CredFlowFollowUpAgent when the lead has no agent config', function () {
    $lead = Lead::factory()->create(['agent_id' => null]);

    $resolved = app(AgentFactory::class)->makeFollowUp($lead);

    expect($resolved)->toBeInstanceOf(CredFlowFollowUpAgent::class);
});
