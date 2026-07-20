<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ConversationSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One service cycle (atendimento) inside a Lead.
 *
 * A Lead is the canonical per-phone aggregate; a ConversationSession segments that
 * lead's history into discrete atendimentos so the funnel and metrics can be scoped
 * per cycle instead of per lead-lifetime. At most one session is open per lead at a
 * time (partial unique index + cache lock in the lifecycle service).
 */
class ConversationSession extends Model
{
    /** @use HasFactory<ConversationSessionFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const OPEN_REASON_FIRST_CONTACT = 'first_contact';

    public const OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL = 'reengagement_after_terminal';

    public const OPEN_REASON_REENGAGEMENT_AFTER_INACTIVITY = 'reengagement_after_inactivity';

    public const OPEN_REASON_CAMPAIGN = 'campaign';

    public const OPEN_REASON_MANUAL = 'manual';

    /** Reasons that mark a returning customer (drives the inbox "Retornante" badge). */
    public const REENGAGEMENT_REASONS = [
        self::OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL,
        self::OPEN_REASON_REENGAGEMENT_AFTER_INACTIVITY,
    ];

    public const OUTCOME_CONVERTED = 'converted';

    public const OUTCOME_LOST = 'lost';

    public const OUTCOME_NO_RESPONSE = 'no_response';

    public const OUTCOME_ABANDONED = 'abandoned';

    public const OUTCOME_MANUAL_CLOSE = 'manual_close';

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'number',
        'status',
        'open_reason',
        'outcome',
        'opened_at',
        'closed_at',
        'last_message_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_message_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function timelineMessages(): HasMany
    {
        return $this->hasMany(ConversationTimelineMessage::class, 'session_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isReengagement(): bool
    {
        return in_array($this->open_reason, self::REENGAGEMENT_REASONS, true);
    }
}
