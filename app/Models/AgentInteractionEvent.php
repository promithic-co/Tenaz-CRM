<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentInteractionEvent extends Model
{
    use HasFactory;

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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
