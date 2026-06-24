<?php

use App\Models\Agent;
use App\Models\AiUsageDaily;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create(['user_id' => $this->user->id]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function backfillMessageRow(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('agent_conversation_messages')->insert(array_merge([
        'id' => $id,
        'conversation_id' => (string) Str::uuid(),
        'user_id' => null,
        'agent' => 'gpt-4o-mini',
        'role' => 'assistant',
        'content' => 'response',
        'attachments' => '',
        'tool_calls' => '',
        'tool_results' => '',
        'usage' => json_encode(['promptTokens' => 100, 'completionTokens' => 40]),
        'meta' => '',
        'created_at' => '2026-01-05 10:00:00',
        'updated_at' => '2026-01-05 10:00:00',
    ], $overrides));

    return $id;
}

test('backfill aggregates assistant usage by tenant/agent/model/date via chunked scan (MEM-1)', function () {
    $lead = Lead::factory()->create(['agent_id' => $this->agent->id, 'tenant_id' => $this->user->tenantId]);

    backfillMessageRow(['user_id' => $lead->id, 'usage' => json_encode(['promptTokens' => 100, 'completionTokens' => 40]), 'created_at' => '2026-02-01 09:00:00']);
    backfillMessageRow(['user_id' => $lead->id, 'usage' => json_encode(['promptTokens' => 50, 'completionTokens' => 20]), 'created_at' => '2026-02-01 11:00:00']);

    $this->artisan('credflow:backfill-ai-usage')->assertSuccessful();

    $row = AiUsageDaily::where('tenant_id', (string) $lead->tenant_id)->where('date', '2026-02-01')->first();

    expect(AiUsageDaily::count())->toBe(1)
        ->and($row)->not->toBeNull()
        ->and($row->total_requests)->toBe(2)
        ->and($row->total_prompt_tokens)->toBe(150)
        ->and($row->total_completion_tokens)->toBe(60);
});

test('backfill skips rows with no matching lead, empty usage, or non-assistant role (MEM-1)', function () {
    $lead = Lead::factory()->create(['agent_id' => $this->agent->id, 'tenant_id' => $this->user->tenantId]);

    backfillMessageRow(['user_id' => 999999, 'usage' => json_encode(['promptTokens' => 10, 'completionTokens' => 5])]); // no matching lead
    backfillMessageRow(['user_id' => $lead->id, 'usage' => json_encode(['completionTokens' => 5])]);                     // no promptTokens
    backfillMessageRow(['user_id' => $lead->id, 'role' => 'user', 'usage' => json_encode(['promptTokens' => 10, 'completionTokens' => 5])]); // excluded by the role filter

    $this->artisan('credflow:backfill-ai-usage')->assertSuccessful();

    expect(AiUsageDaily::count())->toBe(0);
});

test('backfill resolves each message lead from a per-chunk batch fetch (MEM-1)', function () {
    $agentB = Agent::factory()->create(['user_id' => $this->user->id]);
    $leadA = Lead::factory()->create(['agent_id' => $this->agent->id, 'tenant_id' => $this->user->tenantId]);
    $leadB = Lead::factory()->create(['agent_id' => $agentB->id, 'tenant_id' => $this->user->tenantId]);

    backfillMessageRow(['user_id' => $leadA->id, 'created_at' => '2026-03-01 10:00:00']);
    backfillMessageRow(['user_id' => $leadB->id, 'created_at' => '2026-03-01 10:00:00']);

    $this->artisan('credflow:backfill-ai-usage')->assertSuccessful();

    // Distinct agent_id keys → one daily row each, proving every message mapped to its own lead.
    expect(AiUsageDaily::count())->toBe(2)
        ->and(AiUsageDaily::where('agent_id', $this->agent->id)->exists())->toBeTrue()
        ->and(AiUsageDaily::where('agent_id', $agentB->id)->exists())->toBeTrue();
});
