<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\ConversationAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function automationService(): ConversationAutomationService
{
    return new ConversationAutomationService;
}

function tenantAgent(string $tenantId = 'tenant-resolve'): Agent
{
    return Agent::factory()->create(['tenant_id' => $tenantId]);
}

// ─── resolveModesByInstanceId (whatsapp_instance_id keyed) ──────────────────────

it('id-keyed: agentless lead resolves to manual', function (): void {
    $lead = Lead::factory()->create(['agent_id' => null, 'ai_mode' => 'automatic']);

    $modes = automationService()->resolveModesByInstanceId(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_MANUAL);
});

it('id-keyed: explicit ai_mode wins over instance default', function (): void {
    $agent = tenantAgent();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'default_ai_mode' => Lead::AI_MODE_AUTOMATIC,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'agent_id' => $agent->id,
        'ai_mode' => Lead::AI_MODE_ASSISTED,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $modes = automationService()->resolveModesByInstanceId(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_ASSISTED);
});

it('id-keyed: null ai_mode falls back to instance default', function (): void {
    $agent = tenantAgent();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'default_ai_mode' => Lead::AI_MODE_QUALIFY_THEN_HANDOFF,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'agent_id' => $agent->id,
        'ai_mode' => null,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $modes = automationService()->resolveModesByInstanceId(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_QUALIFY_THEN_HANDOFF);
});

it('id-keyed: empty-string ai_mode also falls back to instance default (! $mode guard)', function (): void {
    // ConversasController::resolveModeWithCache:738 guards on `! $mode`, so an empty
    // string is falsy and triggers the instance-default lookup — same as null.
    $agent = tenantAgent();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'default_ai_mode' => Lead::AI_MODE_QUALIFY_THEN_HANDOFF,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'agent_id' => $agent->id,
        'ai_mode' => '',
        'whatsapp_instance_id' => $instance->id,
    ]);

    $modes = automationService()->resolveModesByInstanceId(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_QUALIFY_THEN_HANDOFF);
});

it('id-keyed: invalid ai_mode collapses to automatic', function (): void {
    $agent = tenantAgent();
    $lead = Lead::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'agent_id' => $agent->id,
        'ai_mode' => 'bogus_mode',
        'whatsapp_instance_id' => null,
    ]);

    $modes = automationService()->resolveModesByInstanceId(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_AUTOMATIC);
});

it('id-keyed: empty collection issues zero queries', function (): void {
    DB::enableQueryLog();

    $modes = automationService()->resolveModesByInstanceId(collect());

    expect($modes)->toBe([])
        ->and(DB::getQueryLog())->toHaveCount(0);

    DB::disableQueryLog();
});

// ─── resolveModesByInstanceName (evolution_instance keyed) ──────────────────────

it('name-keyed: agentless lead resolves to manual', function (): void {
    $lead = Lead::factory()->create(['agent_id' => null, 'ai_mode' => 'automatic']);

    $modes = automationService()->resolveModesByInstanceName(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_MANUAL);
});

it('name-keyed: explicit ai_mode wins over instance default', function (): void {
    $agent = tenantAgent();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'default_ai_mode' => Lead::AI_MODE_AUTOMATIC,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'agent_id' => $agent->id,
        'ai_mode' => Lead::AI_MODE_ASSISTED,
        'evolution_instance' => $instance->name,
    ]);

    $modes = automationService()->resolveModesByInstanceName(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_ASSISTED);
});

it('name-keyed: null ai_mode falls back to instance default', function (): void {
    $agent = tenantAgent();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'default_ai_mode' => Lead::AI_MODE_QUALIFY_THEN_HANDOFF,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'agent_id' => $agent->id,
        'ai_mode' => null,
        'evolution_instance' => $instance->name,
    ]);

    $modes = automationService()->resolveModesByInstanceName(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_QUALIFY_THEN_HANDOFF);
});

it('name-keyed: empty-string ai_mode does NOT fall back (?? keeps the empty string → automatic)', function (): void {
    // PipelineController::resolveModeFromMap:280 uses `$lead->ai_mode ?? map->get(...)`.
    // An empty string is not null, so `??` keeps it; the empty string is not a valid
    // AI_MODE, so the validation tail collapses it to AUTOMATIC — the instance default
    // (QUALIFY_THEN_HANDOFF) is intentionally NOT applied. This is the divergence vs
    // the id-keyed resolver.
    $agent = tenantAgent();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'default_ai_mode' => Lead::AI_MODE_QUALIFY_THEN_HANDOFF,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'agent_id' => $agent->id,
        'ai_mode' => '',
        'evolution_instance' => $instance->name,
    ]);

    $modes = automationService()->resolveModesByInstanceName(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_AUTOMATIC);
});

it('name-keyed: invalid ai_mode collapses to automatic', function (): void {
    $agent = tenantAgent();
    $lead = Lead::factory()->create([
        'tenant_id' => $agent->tenant_id,
        'agent_id' => $agent->id,
        'ai_mode' => 'bogus_mode',
        'evolution_instance' => null,
    ]);

    $modes = automationService()->resolveModesByInstanceName(collect([$lead]));

    expect($modes[$lead->id])->toBe(Lead::AI_MODE_AUTOMATIC);
});

it('name-keyed: empty collection issues zero queries', function (): void {
    DB::enableQueryLog();

    $modes = automationService()->resolveModesByInstanceName(collect());

    expect($modes)->toBe([])
        ->and(DB::getQueryLog())->toHaveCount(0);

    DB::disableQueryLog();
});
