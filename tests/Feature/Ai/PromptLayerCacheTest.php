<?php

use App\Ai\Agents\CredFlowAgent;
use App\Models\Lead;
use App\Models\PromptTemplate;
use App\Models\ToolDefinition;
use App\Support\PromptLayerCache;
use Illuminate\Support\Facades\DB;

test('PromptLayerCache serves a cached value and re-resolves after a version bump (SCALE-8)', function () {
    $calls = 0;
    $resolve = function () use (&$calls) {
        $calls++;

        return "X{$calls}";
    };

    expect(PromptLayerCache::remember('t', 'k', $resolve))->toBe('X1')
        ->and(PromptLayerCache::remember('t', 'k', $resolve))->toBe('X1')
        ->and($calls)->toBe(1);

    PromptLayerCache::bump('t');

    expect(PromptLayerCache::remember('t', 'k', $resolve))->toBe('X2')
        ->and($calls)->toBe(2);
});

test('PromptLayerCache caches a null result so the no-match case does not re-query (SCALE-8)', function () {
    $calls = 0;
    $resolve = function () use (&$calls) {
        $calls++;

        return null;
    };

    expect(PromptLayerCache::remember('t', 'k', $resolve))->toBeNull()
        ->and(PromptLayerCache::remember('t', 'k', $resolve))->toBeNull()
        ->and($calls)->toBe(1);
});

test('two version bumps advance the version by exactly two (atomic increment, SCALE-8)', function () {
    expect(PromptLayerCache::version('inc'))->toBe(0);

    PromptLayerCache::bump('inc');
    PromptLayerCache::bump('inc');

    // bump() is an atomic add+increment, so concurrent operator edits can never collapse to a
    // single lost bump that leaves a stale prompt layer cached.
    expect(PromptLayerCache::version('inc'))->toBe(2);
});

test('a version bump is scoped to one tenant (SCALE-8)', function () {
    PromptLayerCache::remember('a', 'k', fn () => 'a1');
    PromptLayerCache::remember('b', 'k', fn () => 'b1');

    PromptLayerCache::bump('a');

    expect(PromptLayerCache::remember('a', 'k', fn () => 'a2'))->toBe('a2')
        ->and(PromptLayerCache::remember('b', 'k', fn () => 'b2'))->toBe('b1');
});

test('webhook tools are read once then served from cache until a tool write busts it (SCALE-8)', function () {
    $tenantId = 'tenant-8';
    $lead = Lead::factory()->create(['tenant_id' => $tenantId, 'agent_id' => null]);

    ToolDefinition::create([
        'tenant_id' => $tenantId,
        'agent_id' => null,
        'slug' => 'wh-1',
        'name' => 'Webhook 1',
        'description' => 'first',
        'type' => 'webhook',
        'config' => ['url' => 'https://example.test/1'],
        'schema' => [],
        'is_active' => true,
    ]);

    $agent = new CredFlowAgent($lead);
    $method = (new ReflectionClass($agent))->getMethod('loadWebhookTools');
    $method->setAccessible(true);

    expect($method->invoke($agent))->toHaveCount(1);

    DB::enableQueryLog();
    $second = $method->invoke($agent);
    $reads = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'tool_definitions'));
    DB::disableQueryLog();

    expect($second)->toHaveCount(1)
        ->and($reads)->toBeEmpty();

    // An operator tool edit bumps the tenant version -> next read re-queries and reflects it.
    ToolDefinition::create([
        'tenant_id' => $tenantId,
        'agent_id' => null,
        'slug' => 'wh-2',
        'name' => 'Webhook 2',
        'description' => 'second',
        'type' => 'webhook',
        'config' => ['url' => 'https://example.test/2'],
        'schema' => [],
        'is_active' => true,
    ]);

    expect($method->invoke($agent))->toHaveCount(2);
});

test('prompt template is cached per turn and re-resolved after an edit (SCALE-8)', function () {
    $tenantId = 'tenant-8b';
    $lead = Lead::factory()->create(['tenant_id' => $tenantId, 'agent_id' => null]);

    PromptTemplate::create([
        'tenant_id' => $tenantId,
        'name' => 'Sys',
        'slug' => 'system-default',
        'type' => 'system',
        'content' => 'V1',
        'is_active' => true,
    ]);

    $agent = new CredFlowAgent($lead);
    $method = (new ReflectionClass($agent))->getMethod('loadPromptTemplate');
    $method->setAccessible(true);

    expect($method->invoke($agent, 'system')->content)->toBe('V1');

    DB::enableQueryLog();
    $method->invoke($agent, 'system');
    $reads = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'prompt_templates') || str_contains($q['query'], 'prompt_experiments'));
    DB::disableQueryLog();

    expect($reads)->toBeEmpty();

    PromptTemplate::where('tenant_id', $tenantId)->where('slug', 'system-default')->first()
        ->update(['content' => 'V2']);

    expect($method->invoke($agent, 'system')->content)->toBe('V2');
});
