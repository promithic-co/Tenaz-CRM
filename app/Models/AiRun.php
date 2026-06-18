<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends Model
{
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
