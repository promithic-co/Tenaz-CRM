<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentInteractionEvent extends Model
{
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'interaction_id',
        'tenant_id',
        'lead_id',
        'agent_id',
        'event_type',
        'event_source',
        'severity',
        'payload_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Retention window for this insert-amplified observability table (GROW-4).
     * Rows older than the configured number of days are dropped by `model:prune`;
     * a window of 0 disables pruning.
     */
    public function prunable(): Builder
    {
        $days = (int) config('laboratory.retention.interaction_events_days', 90);

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
