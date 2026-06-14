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
        'sent' => 2,
        'delivered' => 3,
        'read' => 4,
        'failed' => 5,
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
}
