<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageDaily extends Model
{
    /** @use HasFactory<\Database\Factories\AiUsageDailyFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'tenant_id',
        'agent_id',
        'model',
        'total_requests',
        'total_prompt_tokens',
        'total_completion_tokens',
        'estimated_cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'total_requests' => 'integer',
            'total_prompt_tokens' => 'integer',
            'total_completion_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
