<?php

use App\Actions\ReassignAgentInstanceAction;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('reassigning the instance keeps existing leads pinned to their original agent', function () {
    $user = userWithTenant();

    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    $oldInstance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
    ]);
    $newInstance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'agent_id' => null,
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'whatsapp_instance_id' => $oldInstance->id,
    ]);

    Cache::put("agent_context_instance_{$oldInstance->name}", ['stale' => true], 300);
    Cache::put("agent_context_instance_{$newInstance->name}", ['stale' => true], 300);

    app(ReassignAgentInstanceAction::class)->execute($agent, $user->id, $user->tenantId, $newInstance->id);

    expect($lead->refresh()->agent_id)->toBe($agent->id)
        ->and($oldInstance->refresh()->agent_id)->toBeNull()
        ->and($newInstance->refresh()->agent_id)->toBe($agent->id)
        ->and($agent->refresh()->is_active)->toBeTrue()
        ->and(Cache::has("agent_context_instance_{$oldInstance->name}"))->toBeFalse()
        ->and(Cache::has("agent_context_instance_{$newInstance->name}"))->toBeFalse();
});

test('unlinking the instance leaves leads untouched and deactivates the agent', function () {
    $user = userWithTenant();

    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'whatsapp_instance_id' => $instance->id,
    ]);

    app(ReassignAgentInstanceAction::class)->execute($agent, $user->id, $user->tenantId, null);

    expect($lead->refresh()->agent_id)->toBe($agent->id)
        ->and($instance->refresh()->agent_id)->toBeNull()
        ->and($agent->refresh()->is_active)->toBeFalse();
});
