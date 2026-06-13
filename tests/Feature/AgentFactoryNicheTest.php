<?php

use App\Ai\AgentFactory;
use App\Ai\Agents\CltAgent;
use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\CredFlowBulkAgent;
use App\Ai\Agents\SiapeAgent;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Lead;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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

test('factory falls back to inss when niche is unknown', function () {
    $agent = Agent::factory()->has(AgentConfig::factory()->state(['agent_niche' => 'unknown']), 'config')->create();
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
