<?php

namespace App\Models;

use App\Events\LeadStatusChanged;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTags;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\FollowUpWindowService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use BelongsToTenant, HasFactory, HasTags, SoftDeletes;

    public const AI_MODE_AUTOMATIC = 'automatic';

    public const AI_MODE_MANUAL = 'manual';

    public const AI_MODE_ASSISTED = 'assisted';

    public const AI_MODE_QUALIFY_THEN_HANDOFF = 'qualify_then_handoff';

    public const AI_MODES = [
        self::AI_MODE_AUTOMATIC,
        self::AI_MODE_MANUAL,
        self::AI_MODE_ASSISTED,
        self::AI_MODE_QUALIFY_THEN_HANDOFF,
    ];

    public const STAGE_NEW_INBOUND = 'new_inbound';

    public const STAGE_AI_QUALIFYING = 'ai_qualifying';

    public const STAGE_QUALIFIED_OPPORTUNITY = 'qualified_opportunity';

    public const STAGE_AI_FOLLOWUP = 'ai_followup';

    public const STAGE_HUMAN_PENDING = 'human_pending';

    public const STAGE_HUMAN_ACTIVE = 'human_active';

    public const STAGE_WAITING_CUSTOMER = 'waiting_customer';

    public const HUMAN_HANDOFF_STAGES = [
        self::STAGE_HUMAN_PENDING,
        self::STAGE_HUMAN_ACTIVE,
        self::STAGE_WAITING_CUSTOMER,
    ];

    public const STAGE_PROPOSAL_SENT = 'proposal_sent';

    public const STAGE_WON = 'won';

    public const STAGE_FUTURE_OPPORTUNITY = 'future_opportunity';

    public const STAGE_LOST = 'lost';

    public const OPERATIONAL_STAGES = [
        self::STAGE_NEW_INBOUND,
        self::STAGE_AI_QUALIFYING,
        self::STAGE_QUALIFIED_OPPORTUNITY,
        self::STAGE_AI_FOLLOWUP,
        self::STAGE_HUMAN_PENDING,
        self::STAGE_HUMAN_ACTIVE,
        self::STAGE_WAITING_CUSTOMER,
        self::STAGE_PROPOSAL_SENT,
        self::STAGE_WON,
        self::STAGE_FUTURE_OPPORTUNITY,
        self::STAGE_LOST,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (Lead $lead): void {
            app(DashboardMetricsService::class)->dispatchUpdate((string) $lead->tenant_id);
        });

        static::updating(function (Lead $lead): void {
            if ($lead->isDirty('status')) {
                LeadStatusChanged::dispatch(
                    $lead->id,
                    (string) $lead->tenant_id,
                    (string) $lead->getOriginal('status'),
                    (string) $lead->status,
                );
            }
        });
    }

    /**
     * Timestamps (follow-up / activity):
     * - last_inbound_at: last WhatsApp message from the customer (inbound). Used by ProcessLeadFollowUpJob
     *   to skip sending when the client messaged very recently (race with queued jobs).
     * - last_interaction_at: last meaningful touch for UI, zombie cutoff, and scheduler — inbound processing,
     *   successful follow-up send, dispatch pre-stamp, tools, etc.
     */
    protected $fillable = [
        'tenant_id',
        'agent_id',
        'campaign_id',
        'contact_id',
        'whatsapp',
        'nome',
        'cpf',
        'idade',
        'status',
        'modo',
        'ai_mode',
        'operational_stage',
        'assigned_user_id',
        'ai_paused_until',
        'ai_paused_reason',
        'ai_paused_by',
        'credito_json',
        'documentos_coletados',
        'conversation_id',
        'followup_count',
        'followup_status',
        'last_interaction_at',
        'last_inbound_at',
        'service_window_expires_at',
        'free_entry_point_started_at',
        'free_entry_point_expires_at',
        'conversation_window_source',
        'evolution_instance',
        'whatsapp_instance_id',
        'is_sandbox',
        'sandbox_label',
        'sandbox_system_prompt',
        'experiment_slug',
        'experiment_variant',
        'last_auto_tag_at',
    ];

    /** Scope to exclude sandbox/test leads from production screens. */
    public function scopeProduction($query): Builder
    {
        return $query->where('is_sandbox', false);
    }

    /** Scope to fetch only sandbox/test leads. */
    public function scopeSandbox($query): Builder
    {
        return $query->where('is_sandbox', true);
    }

    /** Scope to filter leads by tenant. */
    public function scopeForTenant($query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Restrict the inbox query to the leads a user is allowed to triage.
     *
     * Privileged users (owner/admin) bypass the restriction entirely. A
     * restricted user sees (a) leads belonging to their own agents, (b) leads
     * assigned to them, and (c) the unassigned/agentless queue so they can pick
     * up new inbound without owner intervention.
     */
    public function scopeVisibleTo($query, User $user): Builder
    {
        if ($user->isOwnerOrAdmin()) {
            return $query;
        }

        if (! $user->isRestrictedUser()) {
            return $query;
        }

        $ownedAgentIds = Agent::query()->where('user_id', $user->id)->pluck('id');

        return $query->where(function ($q) use ($ownedAgentIds, $user): void {
            $q->whereIn('agent_id', $ownedAgentIds)
                ->orWhere('assigned_user_id', $user->id)
                ->orWhere(function ($qq): void {
                    $qq->whereNull('agent_id')->whereNull('assigned_user_id');
                });
        });
    }

    /**
     * Apply the inbox filter set (status / ai_mode / operational_stage /
     * assignment / free-text search) and the sort. The instance filter and
     * visibility restriction are applied by the caller; this scope owns only the
     * validated filter payload.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeInboxFiltered($query, array $filters): Builder
    {
        if ($filters['status'] === 'followup') {
            $query->where('followup_status', 'active');
        } elseif ($filters['status'] !== 'todos') {
            $query->where('status', $filters['status']);
        }

        if ($filters['ai_mode'] !== 'todos') {
            if ($filters['ai_mode'] === 'inherited') {
                $query->whereNull('ai_mode');
            } else {
                $query->where('ai_mode', $filters['ai_mode']);
            }
        }

        if ($filters['stage'] !== 'todos') {
            $query->where('operational_stage', $filters['stage']);
        }

        if ($filters['assigned'] === 'me') {
            $query->where('assigned_user_id', auth()->id());
        } elseif ($filters['assigned'] === 'unassigned') {
            $query->whereNull('assigned_user_id');
        }

        if ($filters['search']) {
            $query->where(function ($q) use ($filters): void {
                $q->where('nome', 'like', "%{$filters['search']}%")
                    ->orWhere('whatsapp', 'like', "%{$filters['search']}%")
                    ->orWhere('cpf', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy($filters['sort'], $filters['direction']);
    }

    protected $casts = [
        'credito_json' => 'array',
        'documentos_coletados' => 'array',
        'last_interaction_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'last_auto_tag_at' => 'datetime',
        'service_window_expires_at' => 'datetime',
        'free_entry_point_started_at' => 'datetime',
        'free_entry_point_expires_at' => 'datetime',
        'ai_paused_until' => 'datetime',
        'is_sandbox' => 'boolean',
    ];

    public function isAiPaused(): bool
    {
        return $this->ai_paused_until !== null && $this->ai_paused_until->isFuture();
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $tenantId = $this->tenant_id ?? 'default';
        $machine = StatusMachine::forTenant($tenantId);

        return $machine->canTransition($this->status, $newStatus);
    }

    public function activateFollowUp(): void
    {
        $this->setFollowUpActive(['followup_count' => 0, 'last_interaction_at' => now()]);
    }

    public function pauseFollowUp(): void
    {
        $this->update(['followup_status' => 'paused']);
    }

    public function disableFollowUp(): void
    {
        $this->update(['followup_status' => 'inactive']);
    }

    public function resumeFollowUp(): void
    {
        $this->setFollowUpActive();
    }

    private function setFollowUpActive(array $extra = []): void
    {
        if (! app(FollowUpWindowService::class)->canSendFreeFormMessage($this)) {
            $this->update(['followup_status' => 'inactive']);

            return;
        }

        $this->update(array_merge(['followup_status' => 'active'], $extra));
    }

    public function customerServiceWindowClosesAt(): ?CarbonInterface
    {
        return app(FollowUpWindowService::class)->windowClosesAt($this);
    }

    public function isInsideCustomerServiceWindow(): bool
    {
        return app(FollowUpWindowService::class)->isInsideCustomerServiceWindow($this);
    }

    public function customerServiceWindowRemainingMinutes(): int
    {
        return app(FollowUpWindowService::class)->remainingMinutes($this);
    }

    public function isQualificado(): bool
    {
        return $this->status === 'qualificado';
    }

    /**
     * True when at least one attached tag has `is_hot = true`.
     *
     * Used by Kanban (Phase 49) and Smart Lists (Phase 51) to elevate
     * priority leads inside a column / list. Filters trashed tags via
     * the SoftDeletes global scope on Tag.
     */
    public function hasHotTag(): bool
    {
        return $this->tags()->where('is_hot', true)->exists();
    }

    public function temCredito(): bool
    {
        return $this->credito_json && ($this->credito_json['status'] ?? '') === 'QUALIFICADO';
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ConversationSession::class);
    }

    /** The single currently-open atendimento, if any (one-open-per-lead invariant). */
    public function openSession(): HasOne
    {
        return $this->hasOne(ConversationSession::class)
            ->where('status', ConversationSession::STATUS_OPEN);
    }

    public function followupMessages(): HasMany
    {
        return $this->hasMany(FollowupMessage::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function whatsappInstance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class, 'whatsapp_instance_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', 'lead');
    }

    public function getCustomField(string $slug): mixed
    {
        $value = $this->customFieldValues()
            ->whereHas('customField', fn ($q) => $q->where('slug', $slug))
            ->with('customField')
            ->first();

        return $value?->getValue();
    }

    public function setCustomField(string $slug, mixed $value): void
    {
        $field = CustomField::forTenant($this->tenant_id)
            ->forEntity('lead')
            ->where('slug', $slug)
            ->first();

        if (! $field) {
            return;
        }

        $column = match ($field->type) {
            'number' => 'value_number',
            'json' => 'value_json',
            'date' => 'value_date',
            'boolean' => 'value_boolean',
            default => 'value_text',
        };

        CustomFieldValue::updateOrCreate(
            ['custom_field_id' => $field->id, 'entity_type' => 'lead', 'entity_id' => $this->id],
            [$column => $value]
        );
    }
}
