<?php

use App\Models\Agent;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\StatusMachine;
use App\Models\User;
use App\Services\PipelineBoardPropsBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

test('board statuses hide statuses that should not appear on the kanban', function () {
    $user = User::factory()->create();
    $machine = StatusMachine::forTenant((string) $user->tenantId);

    $statuses = app(PipelineBoardPropsBuilder::class)->boardStatuses($machine);

    expect(collect($statuses)->pluck('slug'))->not->toContain('sem_credito');
});

test('index props include full column counts while data is cursor paginated', function () {
    $user = User::factory()->create();

    Lead::factory()->count(32)->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
    ]);

    $props = app(PipelineBoardPropsBuilder::class)->buildIndex([], (string) $user->tenantId);

    expect($props)->toHaveKeys(['statuses', 'columns', 'filters', 'tenantId', 'agents', 'instances', 'tagsCatalog'])
        ->and($props['columns']['novo']['count'])->toBe(32)
        ->and($props['columns']['novo']['data'])->toHaveCount(30)
        ->and($props['columns']['novo']['next_cursor'])->not->toBeNull()
        ->and($props['columns'])->not->toHaveKey('sem_credito');
});

test('column props respect search filters and reject hidden or unknown statuses', function () {
    $user = User::factory()->create();

    Lead::factory()->count(2)->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
        'nome' => 'Alpha Lead',
    ]);
    Lead::factory()->count(2)->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
        'nome' => 'Beta Lead',
    ]);

    $builder = app(PipelineBoardPropsBuilder::class);
    $column = $builder->buildColumn(['search' => 'Alpha'], (string) $user->tenantId, 'novo');

    expect($column['data'])->toHaveCount(2)
        ->and(collect($column['data'])->pluck('nome')->all())->each->toContain('Alpha');

    expect(fn () => $builder->buildColumn([], (string) $user->tenantId, 'sem_credito'))
        ->toThrow(NotFoundHttpException::class);
});

test('card shape derives automation state and source label outside the controller', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'tenant_id' => $user->tenantId,
    ]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'name' => 'Campanha Junho',
    ]);

    $campaignLead = Lead::factory()->forAgent($agent)->create([
        'campaign_id' => $campaign->id,
        'modo' => 'receptivo',
        'status' => 'novo',
    ]);
    $campaignLead->load(['campaign', 'tags']);

    $bulkLead = Lead::factory()->forAgent($agent)->create([
        'modo' => 'bulk',
        'status' => 'novo',
    ]);
    $bulkLead->load(['campaign', 'tags']);

    $builder = app(PipelineBoardPropsBuilder::class);

    expect($builder->sourceLabel($campaignLead))->toBe('Campanha Junho')
        ->and($builder->sourceLabel($bulkLead))->toBe('Campanha');

    $activeCard = $builder->toCardShape($bulkLead, [$bulkLead->id => Lead::AI_MODE_AUTOMATIC]);
    $manualCard = $builder->toCardShape($bulkLead, [$bulkLead->id => Lead::AI_MODE_MANUAL]);

    expect($activeCard['automation_state'])->toBe('active')
        ->and($manualCard['automation_state'])->toBe('manual')
        ->and($activeCard['source_label'])->toBe('Campanha');
});
