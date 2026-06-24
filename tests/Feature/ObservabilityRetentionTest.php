<?php

use App\Models\AgentInteractionEvent;
use App\Models\AiRun;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function makeInteractionEvent(array $overrides = []): AgentInteractionEvent
{
    return AgentInteractionEvent::create(array_merge([
        'interaction_id' => (string) Str::uuid(),
        'tenant_id' => 'tenant-prune',
        'event_type' => 'agent_step',
        'event_source' => 'test',
        'severity' => 'info',
        'created_at' => now(),
    ], $overrides));
}

function makeAiRunAged(string $tenantId, CarbonInterface $createdAt): AiRun
{
    $run = AiRun::create([
        'run_id' => (string) Str::uuid(),
        'tenant_id' => $tenantId,
    ]);
    AiRun::where('id', $run->id)->update(['created_at' => $createdAt]);

    return $run;
}

test('model:prune drops interaction events past the retention window and keeps recent ones (GROW-4)', function () {
    config()->set('laboratory.retention.interaction_events_days', 90);

    $old = makeInteractionEvent(['created_at' => now()->subDays(120)]);
    $recent = makeInteractionEvent(['created_at' => now()->subDays(10)]);

    $this->artisan('model:prune', ['--model' => [AgentInteractionEvent::class]])->assertExitCode(0);

    expect(AgentInteractionEvent::find($old->id))->toBeNull()
        ->and(AgentInteractionEvent::find($recent->id))->not->toBeNull();
});

test('model:prune drops ai runs past the retention window and keeps recent ones (GROW-4)', function () {
    config()->set('laboratory.retention.ai_runs_days', 30);

    $old = makeAiRunAged('tenant-prune', now()->subDays(45));
    $recent = makeAiRunAged('tenant-prune', now()->subDays(5));

    $this->artisan('model:prune', ['--model' => [AiRun::class]])->assertExitCode(0);

    expect(AiRun::find($old->id))->toBeNull()
        ->and(AiRun::find($recent->id))->not->toBeNull();
});

test('a zero retention window disables pruning (GROW-4)', function () {
    config()->set('laboratory.retention.interaction_events_days', 0);

    $ancient = makeInteractionEvent(['created_at' => now()->subDays(400)]);

    $this->artisan('model:prune', ['--model' => [AgentInteractionEvent::class]])->assertExitCode(0);

    expect(AgentInteractionEvent::find($ancient->id))->not->toBeNull();
});
