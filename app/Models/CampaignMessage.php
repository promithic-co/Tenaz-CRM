<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessage extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignMessageFactory> */
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

    public function statusOrder(): int
    {
        return match ($this->status) {
            'pending' => 0,
            'queued' => 1,
            'sent' => 2,
            'delivered' => 3,
            'read' => 4,
            'failed' => 5,
            default => -1,
        };
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $newOrder = match ($newStatus) {
            'pending' => 0,
            'queued' => 1,
            'sent' => 2,
            'delivered' => 3,
            'read' => 4,
            'failed' => 5,
            default => -1,
        };

        // failed can happen at any point; otherwise must be forward progression
        if ($newStatus === 'failed') {
            return ! in_array($this->status, ['delivered', 'read']);
        }

        return $newOrder > $this->statusOrder();
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
