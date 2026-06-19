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
        'provider_attempted_at',
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
            'provider_attempted_at' => 'datetime',
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

    /**
     * Stamp the moment a provider POST is about to be attempted. Set only after
     * pre-flight succeeds (instance resolved, payload built) so a pre-send failure
     * never leaves a false in-doubt marker that would block a safe retry.
     */
    public function markProviderAttempted(): void
    {
        $this->update(['provider_attempted_at' => now()]);
    }

    /**
     * Clear the in-doubt marker on a path that PROVES the message was not sent
     * (provider rejected, or connection refused before any bytes left the client),
     * so a normal retry may re-send without tripping the in-doubt guard.
     */
    public function clearProviderAttempt(): void
    {
        $this->update(['provider_attempted_at' => null]);
    }

    /**
     * Terminal-but-unconfirmed state: the provider POST may or may not have reached
     * Meta. We must NOT blindly re-send. The row is resolved later by a webhook status
     * carrying the opaque key, or by manual/automated reconciliation.
     */
    public function markInDoubt(string $error): void
    {
        $this->update([
            'status' => 'in_doubt',
            'last_error' => $error,
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
