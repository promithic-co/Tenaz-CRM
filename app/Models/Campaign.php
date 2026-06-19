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
            'total_sent' => 'integer',
            'total_delivered' => 'integer',
            'total_read' => 'integer',
            'total_failed' => 'integer',
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

    public function incrementCounter(string $field): void
    {
        $this->increment($field);
    }
}
