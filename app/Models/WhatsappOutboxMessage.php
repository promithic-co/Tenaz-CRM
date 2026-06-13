<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappOutboxMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'channel',
        'provider',
        'payload_json',
        'status',
        'idempotency_key',
        'provider_message_id',
        'attempts',
        'last_error',
        'scheduled_at',
        'sent_at',
        'timeline_message_id',
        'source_type',
        'source_id',
        'interaction_id',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function timelineMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationTimelineMessage::class, 'timeline_message_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function markSending(): void
    {
        $this->update([
            'status' => 'sending',
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markSent(?string $providerMessageId): void
    {
        $this->update([
            'status' => 'sent',
            'provider_message_id' => $providerMessageId,
            'last_error' => null,
            'sent_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $error,
        ]);
    }
}
