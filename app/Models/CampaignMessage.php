<?php

namespace App\Models;

use Database\Factories\CampaignMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessage extends Model
{
    /** @use HasFactory<CampaignMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'contact_list_entry_id',
        'provider_message_id',
        'status',
        'provider_attempted_at',
        'error_code',
        'error_subcode',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'template_params_resolved',
    ];

    protected function casts(): array
    {
        return [
            'template_params_resolved' => 'array',
            'provider_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contactListEntry(): BelongsTo
    {
        return $this->belongsTo(ContactListEntry::class);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Lifecycle progression rank. Single source of truth for status ordering.
     *
     * @var array<string, int>
     */
    private const STATUS_ORDER = [
        'pending' => 0,
        'queued' => 1,
        'in_doubt' => 2,
        'sent' => 3,
        'delivered' => 4,
        'read' => 5,
        'failed' => 6,
    ];

    public function statusOrder(): int
    {
        return self::STATUS_ORDER[$this->status] ?? -1;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        // failed can happen at any point; otherwise must be forward progression
        if ($newStatus === 'failed') {
            return ! in_array($this->status, ['delivered', 'read'], true);
        }

        return (self::STATUS_ORDER[$newStatus] ?? -1) > $this->statusOrder();
    }

    public function markSent(string $providerMessageId): void
    {
        $this->update([
            'status' => 'sent',
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
        ]);
    }

    public function markDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function markFailed(string $errorCode, string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    /**
     * Stamp the moment a provider POST is about to be attempted. Set only after
     * pre-flight succeeds so a pre-send failure never leaves a false in-doubt marker.
     */
    public function markProviderAttempted(): void
    {
        $this->update(['provider_attempted_at' => now()]);
    }

    /**
     * Clear the in-doubt marker on a path that PROVES the message was not sent
     * (Meta rejected, or connection refused before any bytes left the client), so a
     * normal retry/release may re-send without tripping the in-doubt guard.
     */
    public function clearProviderAttempt(): void
    {
        $this->update(['provider_attempted_at' => null]);
    }

    /**
     * Ambiguous send: the provider POST may or may not have reached Meta. Do NOT
     * blindly re-send. The row carries no wamid (response was lost) and is resolved
     * later by a webhook status echoing the opaque key, or by reconciliation.
     */
    public function markInDoubt(string $errorMessage): void
    {
        $this->update([
            'status' => 'in_doubt',
            'error_code' => 'IN_DOUBT',
            'error_message' => $errorMessage,
        ]);
    }
}
