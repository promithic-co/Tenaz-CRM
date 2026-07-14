<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'whatsapp_instance_id',
        'contact_list_id',
        'whatsapp_template_id',
        'name',
        'status',
        'template_params_mapping',
        'daily_limit',
        'delay_between_ms',
        'error_threshold_percent',
        'scheduled_at',
        'started_at',
        'completed_at',
        'paused_at',
        'total_recipients',
        'total_sent',
        'total_delivered',
        'total_read',
        'total_failed',
        'failure_reason',
        'pause_reason_code',
        'paused_from_status',
        'risk_acknowledged_at',
        'risk_acknowledged_by',
    ];

    /**
     * Aggregate aliases injected by scopeWithCounters() to back the derived counter
     * accessors without an N+1 — internal, never serialized to the client.
     *
     * @var list<string>
     */
    protected $hidden = ['agg_sent', 'agg_delivered', 'agg_read', 'agg_failed', 'agg_skipped'];

    /**
     * Memoized message-derived counters for this instance.
     *
     * @var array{sent: int, delivered: int, read: int, failed: int, skipped: int}|null
     */
    private ?array $countersCache = null;

    protected function casts(): array
    {
        return [
            'template_params_mapping' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime',
            'risk_acknowledged_at' => 'datetime',
            'total_recipients' => 'integer',
            'daily_limit' => 'integer',
            'delay_between_ms' => 'integer',
            'error_threshold_percent' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function whatsappInstance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class);
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeScheduledAndReady(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now());
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function canStart(): bool
    {
        return in_array($this->status, ['draft', 'scheduled']);
    }

    public function canPause(): bool
    {
        return $this->status === 'sending';
    }

    public function canResume(): bool
    {
        return $this->status === 'paused';
    }

    public function deliveryRate(): float
    {
        if ($this->total_sent <= 0) {
            return 0.0;
        }

        return round(($this->total_delivered / $this->total_sent) * 100, 2);
    }

    public function readRate(): float
    {
        if ($this->total_delivered <= 0) {
            return 0.0;
        }

        return round(($this->total_read / $this->total_delivered) * 100, 2);
    }

    public function failureRate(): float
    {
        if ($this->total_sent <= 0) {
            return 0.0;
        }

        return round(($this->total_failed / $this->total_sent) * 100, 2);
    }

    /**
     * Derived counter accessors (SCALE-1b). The denormalized total_* columns are no
     * longer written on the hot send/delivery path — that was ~3 single-row UPDATEs per
     * message from concurrent send + delivery-webhook workers, the per-campaign throughput
     * ceiling. The live message rows in campaign_messages (indexed) are the source of
     * truth: count(sent_at) etc. A status='failed' send-time failure has no sent_at, so it
     * counts toward failed but not sent — matching the old increment semantics exactly. Use
     * scopeWithCounters() for list views to avoid an N+1; single reads derive in one query.
     */
    public function getTotalSentAttribute(): int
    {
        return $this->resolveCounter('sent');
    }

    public function getTotalDeliveredAttribute(): int
    {
        return $this->resolveCounter('delivered');
    }

    public function getTotalReadAttribute(): int
    {
        return $this->resolveCounter('read');
    }

    public function getTotalFailedAttribute(): int
    {
        return $this->resolveCounter('failed');
    }

    /**
     * Consent-suppressed sends (CAMP-05). Deliberately outside total_failed (failureRate
     * and the auto-pause must not react to opt-outs) and outside total_sent.
     */
    public function getTotalSkippedAttribute(): int
    {
        return $this->resolveCounter('skipped');
    }

    /**
     * Eager-load the four message-derived counters as agg_* aliases in a single query of
     * correlated subqueries, so listing campaigns does not fire a per-row aggregate.
     */
    public function scopeWithCounters(Builder $query): Builder
    {
        return $query->withCount([
            'messages as agg_sent' => fn (Builder $q): Builder => $q->whereNotNull('sent_at'),
            'messages as agg_delivered' => fn (Builder $q): Builder => $q->whereNotNull('delivered_at'),
            'messages as agg_read' => fn (Builder $q): Builder => $q->whereNotNull('read_at'),
            'messages as agg_failed' => fn (Builder $q): Builder => $q->where('status', 'failed'),
            'messages as agg_skipped' => fn (Builder $q): Builder => $q->where('status', 'skipped'),
        ]);
    }

    private function resolveCounter(string $key): int
    {
        $aggKey = 'agg_'.$key;

        if (array_key_exists($aggKey, $this->attributes)) {
            return (int) $this->attributes[$aggKey];
        }

        return $this->deriveCounters()[$key];
    }

    /**
     * @return array{sent: int, delivered: int, read: int, failed: int}
     */
    private function deriveCounters(): array
    {
        if ($this->countersCache !== null) {
            return $this->countersCache;
        }

        if (! $this->exists) {
            return $this->countersCache = ['sent' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $row = $this->messages()
            ->selectRaw('count(sent_at) as sent_count, count(delivered_at) as delivered_count, count(read_at) as read_count, count(case when status = ? then 1 end) as failed_count, count(case when status = ? then 1 end) as skipped_count', ['failed', 'skipped'])
            ->first();

        return $this->countersCache = [
            'sent' => (int) ($row->sent_count ?? 0),
            'delivered' => (int) ($row->delivered_count ?? 0),
            'read' => (int) ($row->read_count ?? 0),
            'failed' => (int) ($row->failed_count ?? 0),
            'skipped' => (int) ($row->skipped_count ?? 0),
        ];
    }
}
