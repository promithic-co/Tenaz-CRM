<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedInteraction extends Model
{
    protected $fillable = [
        'lead_id', 'agent_id', 'error_tag', 'error_source',
        'error_message', 'context', 'status', 'retry_count',
        'next_retry_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'next_retry_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('next_retry_at', '<=', now());
    }

    public function scopeInBusinessHours(Builder $query): Builder
    {
        $start = config('laboratory.retry.business_hours_start', '08:00');
        $end = config('laboratory.retry.business_hours_end', '18:00');
        $now = now()->format('H:i');

        return $query->when(
            $now < $start || $now > $end,
            fn ($q) => $q->whereRaw('1 = 0')
        );
    }

    public function markRetrying(): void
    {
        $this->update(['status' => 'retrying']);
    }

    public function markResolved(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function markEscalated(): void
    {
        $this->update(['status' => 'escalated']);
    }

    public function scheduleNextRetry(): void
    {
        $backoffs = config('laboratory.retry.backoff_minutes', [15, 60, 240]);
        $delay = $backoffs[$this->retry_count] ?? end($backoffs);

        $this->update([
            'status' => 'pending',
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => now()->addMinutes($delay),
        ]);
    }
}
