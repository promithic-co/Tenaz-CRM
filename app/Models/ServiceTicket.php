<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceTicket extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';

    public const STATUS_WAITING_INTERNAL = 'waiting_internal';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const TYPE_ESCALATION = 'escalation';

    public const TYPE_NO_CREDIT = 'no_credit';

    public const RESOLUTION_CONVERTED = 'converted';

    public const RESOLUTION_LOST = 'lost';

    public const RESOLUTION_RETURNED_TO_AI = 'returned_to_ai';

    public const RESOLUTION_MANUAL_KEEP = 'manual_keep';

    public const RESOLUTION_DUPLICATE = 'duplicate';

    public const RESOLUTION_NO_RESPONSE = 'no_response';

    public const ACTIVE_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ASSIGNED,
        self::STATUS_WAITING_CUSTOMER,
        self::STATUS_WAITING_INTERNAL,
    ];

    public const CLAIMABLE_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ASSIGNED,
    ];

    private const STATUS_ALIASES = [
        'aberto' => self::STATUS_OPEN,
        'resolvido' => self::STATUS_RESOLVED,
        'fechado' => self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'tenant_id', 'lead_id', 'assigned_user_id', 'type', 'status', 'priority', 'sla_due_at',
        'claimed_at', 'first_response_at', 'resolved_at', 'closed_at',
        'resolution_reason', 'resolution_notes', 'last_customer_message_at',
        'last_operator_message_at', 'metadata', 'reason', 'summary',
        'credit_available', 'chosen_product', 'total_value',
        'installment_value', 'observations',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'claimed_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_customer_message_at' => 'datetime',
            'last_operator_message_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceTicket $ticket): void {
            if (empty($ticket->tenant_id) && $ticket->lead_id) {
                $ticket->tenant_id = Lead::find($ticket->lead_id)?->tenant_id ?? 'default';
            }
        });
    }

    /** Scope to filter tickets by tenant. */
    public function scopeForTenant($query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeActiveEscalation($query, int $leadId): Builder
    {
        return $query->where('lead_id', $leadId)
            ->where('type', self::TYPE_ESCALATION)
            ->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public static function normalizeStatus(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return $status;
        }

        return self::STATUS_ALIASES[$status] ?? $status;
    }

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = self::normalizeStatus($value);
    }
}
