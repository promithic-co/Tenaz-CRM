<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationTimelineMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'conversation_id',
        'direction',
        'sender_type',
        'channel',
        'body',
        'media',
        'status',
        'source',
        'interaction_id',
        'provider_message_id',
        'synced_to_agent_at',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'synced_to_agent_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
