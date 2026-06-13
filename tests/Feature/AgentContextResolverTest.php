<?php

use App\Models\Agent;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\AgentContextResolver;
use Illuminate\Support\Facades\Cache;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('resolves agent and tenant from instance name', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'agent_id' => $agent->id,
        'name' => 'MyInstance',
    ]);

    $resolver = app(AgentContextResolver::class);
    $context = $resolver->resolveFromInstanceName('MyInstance');

    expect($context['tenant_id'])->toBe($user->tenantId)
        ->and($context['agent_id'])->toBe($agent->id)
        ->and($context['user_id'])->toBe($user->id);
});

test('returns default context when instance name is null', function () {
    $resolver = app(AgentContextResolver::class);
    $context = $resolver->resolveFromInstanceName(null);

    expect($context['tenant_id'])->toBe('default')
        ->and($context['agent_id'])->toBeNull();
});

test('derives tenant from agent owner not instance user_id', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $owner->id]);

    // Instance belongs to otherUser but agent belongs to owner
    WhatsappInstance::factory()->create([
        'user_id' => $otherUser->id,
        'agent_id' => $agent->id,
        'name' => 'CrossTenantInstance',
    ]);

    $resolver = app(AgentContextResolver::class);
    $context = $resolver->resolveFromInstanceName('CrossTenantInstance');

    // tenant_id should follow the agent's owner, not instance user_id
    expect($context['tenant_id'])->toBe($owner->tenantId)
        ->and($context['user_id'])->toBe($owner->id)
        ->and($context['agent_id'])->toBe($agent->id);
});

test('prefers instance with assigned agent when duplicate names exist', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $agent2 = Agent::factory()->create(['user_id' => $user2->id]);

    // Instance without agent (user1)
    WhatsappInstance::factory()->create([
        'user_id' => $user1->id,
        'agent_id' => null,
        'name' => 'DuplicateName',
    ]);

    // Instance with agent (user2) — should be preferred
    WhatsappInstance::factory()->create([
        'user_id' => $user2->id,
        'agent_id' => $agent2->id,
        'name' => 'DuplicateName',
    ]);

    $resolver = app(AgentContextResolver::class);
    $context = $resolver->resolveFromInstanceName('DuplicateName');

    expect($context['tenant_id'])->toBe($user2->tenantId)
        ->and($context['agent_id'])->toBe($agent2->id);
});

test('caches resolved context for one hour', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'agent_id' => $agent->id,
        'name' => 'CachedInstance',
    ]);

    $resolver = app(AgentContextResolver::class);
    $resolver->resolveFromInstanceName('CachedInstance');

    expect(Cache::has('agent_context_instance_CachedInstance'))->toBeTrue();
});
