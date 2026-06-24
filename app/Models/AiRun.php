<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends Model
{
    use MassPrunable;

    protected $fillable = [
        'run_id',
        'trace_id',
        'tenant_id',
        'lead_id',
        'conversation_id',
        'agent_id',
        'agent_name',
        'architecture_version',
        'prompt_hash',
        'skill_hash',
        'model',
        'started_at',
        'ended_at',
        'duration_ms',
        'llm_calls',
        'tool_calls',
        'input_tokens',
        'output_tokens',
        'estimated_cost_usd',
        'status',
        'outcome',
        'error_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_ms' => 'integer',
            'llm_calls' => 'integer',
            'tool_calls' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'metadata' => 'array',
        ];
    }

    /**
     * Retention window for this high-volume run-metrics table (GROW-4). Rows older
     * than the configured number of days are dropped by `model:prune`; a window of
     * 0 disables pruning. Keyed on created_at (row age), not the analysis window.
     */
    public function prunable(): Builder
    {
        $days = (int) config('laboratory.retention.ai_runs_days', 90);

        if ($days <= 0) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<=', now()->subDays($days));
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
