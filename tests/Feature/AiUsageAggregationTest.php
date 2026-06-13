<?php

use App\Models\Agent;
use App\Models\AiUsageDaily;
use App\Models\Lead;
use App\Models\User;
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
