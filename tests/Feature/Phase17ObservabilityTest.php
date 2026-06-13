<?php

use App\Models\Lead;
use App\Models\PromptExperiment;
use App\Models\PromptTemplate;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $tenant = Tenant::create(['name' => 'Observability Tenant']);
    $this->user->tenants()->attach($tenant->id, ['role' => 'owner']);
    $this->tenantId = (string) $tenant->id;
    $this->actingAs($this->user);
});

// ─── Health API ───────────────────────────────────────────────────────────────

test('health endpoint returns json with all checks', function () {
    $response = $this->getJson('/api/health');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => ['database', 'cache', 'queue', 'disk'],
        ])
        ->assertJsonPath('status', 'healthy');
});

test('health endpoint checks database connectivity', function () {
    $response = $this->getJson('/api/health');

    $response->assertJsonPath('checks.database.status', 'ok');
});

test('health endpoint checks cache connectivity', function () {
    $response = $this->getJson('/api/health');

    $response->assertJsonPath('checks.cache.status', 'ok');
});

// ─── Laboratory Health Page ────────────────────────────────────────────────

test('laboratory health page loads', function () {
    $this->get('/laboratory/health')->assertOk();
});

// ─── A/B Experiments ─────────────────────────────────────────────────────────

test('experiment assigns variant to lead deterministically', function () {
    $templateA = PromptTemplate::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Template A',
        'slug' => 'system-a',
        'type' => 'system',
        'content' => 'Variant A prompt',
        'is_active' => true,
    ]);

    $templateB = PromptTemplate::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Template B',
        'slug' => 'system-b',
        'type' => 'system',
        'content' => 'Variant B prompt',
        'is_active' => true,
    ]);

    $experiment = PromptExperiment::create([
        'tenant_id' => $this->tenantId,
        'slug' => 'test-ab',
        'name' => 'Test A/B',
        'prompt_type' => 'system',
        'is_active' => true,
        'variants' => [
            ['slug' => 'a', 'template_slug' => 'system-a', 'weight' => 5],
            ['slug' => 'b', 'template_slug' => 'system-b', 'weight' => 5],
        ],
    ]);

    $lead = Lead::factory()->create(['tenant_id' => $this->tenantId]);

    $variant1 = $experiment->assignVariant($lead);
    $variant2 = $experiment->assignVariant($lead); // must be same (sticky)

    expect($variant1)->toBe($variant2)
        ->and($variant1)->toBeIn(['a', 'b']);
});

test('experiment assignment persists on lead', function () {
    $experiment = PromptExperiment::create([
        'tenant_id' => $this->tenantId,
        'slug' => 'sticky-test',
        'name' => 'Sticky Test',
        'prompt_type' => 'system',
        'is_active' => true,
        'variants' => [
            ['slug' => 'control', 'template_slug' => 'control-tpl', 'weight' => 10],
        ],
    ]);

    $lead = Lead::factory()->create(['tenant_id' => $this->tenantId]);
    $experiment->assignVariant($lead);
    $lead->refresh();

    expect($lead->experiment_slug)->toBe('sticky-test')
        ->and($lead->experiment_variant)->toBe('control');
});

test('experiment results aggregates per variant', function () {
    $experiment = PromptExperiment::create([
        'tenant_id' => $this->tenantId,
        'slug' => 'results-test',
        'name' => 'Results Test',
        'prompt_type' => 'followup',
        'is_active' => true,
        'variants' => [
            ['slug' => 'v1', 'template_slug' => 'tpl-v1', 'weight' => 5],
            ['slug' => 'v2', 'template_slug' => 'tpl-v2', 'weight' => 5],
        ],
    ]);

    Lead::factory()->create(['tenant_id' => $this->tenantId, 'experiment_slug' => 'results-test', 'experiment_variant' => 'v1', 'status' => 'convertido']);
    Lead::factory()->create(['tenant_id' => $this->tenantId, 'experiment_slug' => 'results-test', 'experiment_variant' => 'v1', 'status' => 'novo']);
    Lead::factory()->create(['tenant_id' => $this->tenantId, 'experiment_slug' => 'results-test', 'experiment_variant' => 'v2', 'status' => 'convertido']);

    $results = $experiment->results();

    expect($results->has('v1'))->toBeTrue()
        ->and($results['v1']['assigned'])->toBe(2)
        ->and($results['v1']['converted'])->toBe(1)
        ->and($results['v2']['assigned'])->toBe(1)
        ->and($results['v2']['converted'])->toBe(1);
});

test('experiment-report command lists experiments', function () {
    PromptExperiment::create([
        'tenant_id' => $this->tenantId,
        'slug' => 'cmd-exp',
        'name' => 'Command Experiment',
        'prompt_type' => 'system',
        'is_active' => true,
        'variants' => [],
    ]);

    $this->artisan('credflow:experiment-report')->assertSuccessful();
});

test('experiment-report command shows results for slug', function () {
    PromptExperiment::create([
        'tenant_id' => $this->tenantId,
        'slug' => 'show-results',
        'name' => 'Show Results',
        'prompt_type' => 'system',
        'is_active' => true,
        'variants' => [],
    ]);

    $this->artisan('credflow:experiment-report', ['slug' => 'show-results'])->assertSuccessful();
});

test('experiment-report command fails for unknown slug', function () {
    $this->artisan('credflow:experiment-report', ['slug' => 'does-not-exist'])->assertFailed();
});

// ─── Health Artisan Command ───────────────────────────────────────────────────

test('credflow:health command passes in test environment', function () {
    $this->artisan('credflow:health')->assertSuccessful();
});

test('credflow:health command outputs json when requested', function () {
    $this->artisan('credflow:health --json')->assertSuccessful();
});
