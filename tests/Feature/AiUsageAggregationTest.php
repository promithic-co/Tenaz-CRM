<?php

use App\Models\Agent;
use App\Models\AiRun;
use App\Models\AiUsageDaily;
use App\Models\Lead;
use App\Models\User;
use App\Services\AiRunRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create(['user_id' => $this->user->id]);
});

test('aggregates AI usage from conversation messages', function () {
    $conversationId = (string) Str::uuid();
    $yesterday = now()->subDay()->toDateString();

    Lead::factory()->create([
        'agent_id' => $this->agent->id,
        'tenant_id' => $this->user->tenantId,
        'conversation_id' => $conversationId,
    ]);

    DB::table('agent_conversation_messages')->insert([
        [
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => null,
            'agent' => 'AriaAgent',
            'role' => 'assistant',
            'content' => 'Hello, response 1',
            'attachments' => '',
            'tool_calls' => '',
            'tool_results' => '',
            'usage' => json_encode(['promptTokens' => 500, 'completionTokens' => 200]),
            'meta' => '',
            'created_at' => $yesterday.' 10:00:00',
            'updated_at' => $yesterday.' 10:00:00',
        ],
        [
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => null,
            'agent' => 'AriaAgent',
            'role' => 'assistant',
            'content' => 'Hello, response 2',
            'attachments' => '',
            'tool_calls' => '',
            'tool_results' => '',
            'usage' => json_encode(['promptTokens' => 300, 'completionTokens' => 100]),
            'meta' => '',
            'created_at' => $yesterday.' 11:00:00',
            'updated_at' => $yesterday.' 11:00:00',
        ],
        [
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => null,
            'agent' => 'AriaAgent',
            'role' => 'user',
            'content' => 'User message — should be excluded',
            'attachments' => '',
            'tool_calls' => '',
            'tool_results' => '',
            'usage' => '',
            'meta' => '',
            'created_at' => $yesterday.' 10:30:00',
            'updated_at' => $yesterday.' 10:30:00',
        ],
    ]);

    $this->artisan('credflow:aggregate-usage', ['--date' => $yesterday])
        ->assertSuccessful();

    $usage = AiUsageDaily::where('date', $yesterday)
        ->where('tenant_id', $this->user->tenantId)
        ->first();

    expect($usage)->not->toBeNull()
        ->and($usage->total_requests)->toBe(2)
        ->and($usage->total_prompt_tokens)->toBe(800)
        ->and($usage->total_completion_tokens)->toBe(300)
        ->and((float) $usage->estimated_cost_usd)->toBeGreaterThan(0);
});

test('cost calculation uses model rates from config', function () {
    // gpt-4o: prompt=0.0025/1K, completion=0.01/1K
    // Expected: (1000/1000 * 0.0025) + (500/1000 * 0.01) = 0.0025 + 0.005 = 0.0075
    $rates = config('credflow.model_costs.gpt-4o');
    $expected = (1000 / 1000 * $rates['prompt']) + (500 / 1000 * $rates['completion']);

    expect($expected)->toBe(0.0075);
});

test('ai usage page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get('/laboratory/ai-usage')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/AiUsage')
            ->has('dailyUsage')
            ->has('byModel')
            ->has('totalMonth')
            ->has('runs')
            ->has('filters')
        );
});

test('records minimal ai run metrics', function () {
    $lead = Lead::factory()->create([
        'agent_id' => $this->agent->id,
        'tenant_id' => $this->user->tenantId,
        'conversation_id' => (string) Str::uuid(),
    ]);

    $runId = (string) Str::uuid();
    $recorder = app(AiRunRecorder::class);

    $recorder->start($runId, $lead, 'CredFlowAgent', 'legacy_prompt');
    $recorder->recordModelCall($runId, 'gpt-4o-mini', 1000, 500, hash('sha256', 'prompt'));
    $recorder->recordToolCalls($runId, 2);
    $recorder->finish($runId, 'success', 'replied');

    $run = AiRun::where('run_id', $runId)->first();

    expect($run)->not->toBeNull()
        ->and($run->architecture_version)->toBe('legacy_prompt')
        ->and($run->llm_calls)->toBe(1)
        ->and($run->tool_calls)->toBe(2)
        ->and($run->input_tokens)->toBe(1000)
        ->and($run->output_tokens)->toBe(500)
        ->and((float) $run->estimated_cost_usd)->toBeGreaterThan(0)
        ->and($run->status)->toBe('success')
        ->and($run->outcome)->toBe('replied');
});

test('ai usage page includes filtered ai runs', function () {
    AiRun::create([
        'run_id' => (string) Str::uuid(),
        'trace_id' => (string) Str::uuid(),
        'tenant_id' => (string) $this->user->tenantId,
        'agent_id' => $this->agent->id,
        'agent_name' => 'CredFlowAgent',
        'architecture_version' => 'folder_skills',
        'model' => 'gpt-4o-mini',
        'started_at' => now(),
        'ended_at' => now(),
        'duration_ms' => 1200,
        'llm_calls' => 1,
        'tool_calls' => 1,
        'input_tokens' => 100,
        'output_tokens' => 50,
        'estimated_cost_usd' => 0.001,
        'status' => 'success',
        'outcome' => 'asked_next_question',
    ]);

    $this->actingAs($this->user)
        ->get('/laboratory/ai-usage?architecture_version=folder_skills')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/AiUsage')
            ->has('runs', 1)
            ->where('runs.0.architecture_version', 'folder_skills')
            ->where('runs.0.status', 'success')
        );
});

test('aggregate command updates existing records on re-run', function () {
    $conversationId = (string) Str::uuid();
    $yesterday = now()->subDay()->toDateString();

    Lead::factory()->create([
        'agent_id' => $this->agent->id,
        'tenant_id' => $this->user->tenantId,
        'conversation_id' => $conversationId,
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => null,
        'agent' => 'AriaAgent',
        'role' => 'assistant',
        'content' => 'test',
        'attachments' => '',
        'tool_calls' => '',
        'tool_results' => '',
        'usage' => json_encode(['promptTokens' => 100, 'completionTokens' => 50]),
        'meta' => '',
        'created_at' => $yesterday.' 10:00:00',
        'updated_at' => $yesterday.' 10:00:00',
    ]);

    $this->artisan('credflow:aggregate-usage', ['--date' => $yesterday])->assertSuccessful();
    $this->artisan('credflow:aggregate-usage', ['--date' => $yesterday])->assertSuccessful();

    expect(AiUsageDaily::where('date', $yesterday)->count())->toBe(1);
});

test('aggregation counts only the target calendar day via a sargable range (GROW-3)', function () {
    $conversationId = (string) Str::uuid();
    $target = now()->subDay()->toDateString();

    Lead::factory()->create([
        'agent_id' => $this->agent->id,
        'tenant_id' => $this->user->tenantId,
        'conversation_id' => $conversationId,
    ]);

    $row = fn (string $createdAt, int $prompt): array => [
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => null,
        'agent' => 'AriaAgent',
        'role' => 'assistant',
        'content' => 'x',
        'attachments' => '',
        'tool_calls' => '',
        'tool_results' => '',
        'usage' => json_encode(['promptTokens' => $prompt, 'completionTokens' => 0]),
        'meta' => '',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ];

    DB::table('agent_conversation_messages')->insert([
        $row($target.' 00:00:00', 100),                                  // first instant — included
        $row($target.' 23:59:59', 100),                                  // last instant — included
        $row(now()->subDays(2)->toDateString().' 23:59:59', 999),        // day before — excluded
        $row(now()->toDateString().' 00:00:00', 999),                    // next day boundary — excluded
    ]);

    $this->artisan('credflow:aggregate-usage', ['--date' => $target])->assertSuccessful();

    $usage = AiUsageDaily::where('date', $target)
        ->where('tenant_id', $this->user->tenantId)
        ->first();

    expect($usage)->not->toBeNull()
        ->and($usage->total_requests)->toBe(2)
        ->and($usage->total_prompt_tokens)->toBe(200);
});
