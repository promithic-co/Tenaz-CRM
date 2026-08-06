<?php

namespace App\Models;

use App\Events\LeadStatusChanged;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTags;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\FollowUpWindowService;
use App\Services\WhatsApp\PhoneNumberValidator;
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

    public const INBOX_GROUP_ALL = 'todas';

    public const INBOX_GROUP_QUEUE = 'fila';

    public const INBOX_GROUP_MINE = 'minhas';

    public const INBOX_GROUP_AI = 'ia';

    /**
     * Leads created by a campaign send that have never replied. Segregated from every
     * other tab — a 50k fan-out would otherwise bury the atendente's real queue — and
     * a lead leaves this group on its own the moment last_inbound_at is stamped.
     */
    public const INBOX_GROUP_SENDS = 'envios';

    /** Countable inbox tabs, in display order. "todas" is the absence of a group filter. */
    public const INBOX_GROUPS = [
        self::INBOX_GROUP_QUEUE,
        self::INBOX_GROUP_MINE,
        self::INBOX_GROUP_AI,
        self::INBOX_GROUP_SENDS,
    ];

    /**
     * Columns the inbox is allowed to sort by. Owned here rather than by the FormRequest
     * because scopeInboxFiltered interpolates the choice into raw SQL and must be able to
     * vouch for it on its own, whatever built the filter array.
     *
     * @var list<string>
     */
    public const INBOX_SORT_COLUMNS = ['nome', 'status', 'followup_count', 'last_interaction_at', 'operational_stage'];

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
     * Every lead that could be this subscriber, whichever 9th-digit spelling it was
     * written under.
     *
     * Brazilian mobiles gained a mandatory 9th digit in 2012 and the sources feeding this
     * system disagree about it — an inbound webhook writes 12 digits where a CSV import
     * wrote 13. Matching the exact string is what split one person into two conversations:
     * `leads_tenant_whatsapp_active_unique` compares strings, so it sees two subscribers
     * and lets both in. Contacts have resolved across spellings since ContactSyncService;
     * leads, which are what /conversas actually lists, did not.
     *
     * Callers that need one row want {@see scopeOrderByPhoneMatch} on top; callers that
     * act on the person (pausing the AI, renaming) want all of them and should not order.
     */
    public function scopeForPhoneVariants($query, ?string $phone): Builder
    {
        $variants = PhoneNumberValidator::variants($phone);

        // An empty variant set means the input carried no digits. Left to whereIn that is
        // a false-y match, but stating it keeps a blank phone from ever reading as "any".
        if ($variants === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('whatsapp', $variants);
    }

    /**
     * Order the variant matches so ->first() returns the row the caller means: the exact
     * spelling it asked for, then the canonical one, then the oldest.
     *
     * Same precedence as ContactSyncService::findAcrossPhoneVariants, and for the same
     * reason: where history already holds both spellings, a lookup must keep returning
     * whichever row its caller has been using. Consolidating the pair is the dedupe
     * command's job, which merges the data instead of silently relinking it.
     *
     * Deliberately does not break the remaining tie — callers disagree about whether the
     * oldest or the newest row wins, and this scope must not silently overrule them.
     */
    public function scopeOrderByPhoneMatch($query, ?string $phone): Builder
    {
        $exact = (string) $phone;
        $canonical = PhoneNumberValidator::canonical($phone) ?? $exact;

        return $query->orderByRaw(
            'CASE WHEN whatsapp = ? THEN 0 WHEN whatsapp = ? THEN 1 ELSE 2 END',
            [$exact, $canonical],
        );
    }

    /**
     * The lead for this subscriber, created under the canonical spelling when none exists.
     *
     * The `firstOrCreate(['whatsapp' => $phone])` this replaces matched the exact string,
     * so an inbound that had already opened a conversation under the other 9th-digit form
     * got a second one. Callers still own their own lock; key it on
     * {@see PhoneNumberValidator::canonical()} or the two spellings take different locks
     * and race right past this.
     *
     * @param  array<string, mixed>  $attributes  applied only when the row is created
     */
    public static function firstOrCreateForPhone(string $tenantId, string $phone, array $attributes = []): self
    {
        $existing = self::query()
            ->where('tenant_id', $tenantId)
            ->forPhoneVariants($phone)
            ->orderByPhoneMatch($phone)
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return self::create([
            ...$attributes,
            'tenant_id' => $tenantId,
            'whatsapp' => PhoneNumberValidator::canonical($phone) ?? $phone,
        ]);
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
     * Restrict the inbox to one triage group.
     *
     * The three triage groups are mutually exclusive by construction, so a lead
     * never shows up under two tabs and the counters always sum to at most the total:
     *
     * - fila:   nobody owns it and an escalation ticket is still unclaimed
     * - minhas: the actor owns it
     * - ia:     nobody owns it and no escalation is active
     *
     * Everything else (owned by a teammate, or unowned with an escalation that
     * is claimed but not yet resolved) stays reachable only through "todas".
     *
     * "envios" sits outside that triage entirely: it holds campaign sends nobody
     * has answered, which scopeInboxFiltered subtracts from every other tab. It is
     * a record of what went out, not work waiting to be picked up.
     *
     * Deliberately expressed with columns and EXISTS subqueries only: the
     * effective AI mode is resolved in PHP and the manual pause partly lives in
     * the cache, so neither can back a tab counter without lying about it.
     */
    public function scopeInGroup($query, string $group, ?int $actorId): Builder
    {
        $activeEscalation = fn ($ticketQuery) => $ticketQuery
            ->where('type', ServiceTicket::TYPE_ESCALATION)
            ->whereIn('status', ServiceTicket::ACTIVE_STATUSES);

        return match ($group) {
            self::INBOX_GROUP_QUEUE => $query
                ->whereNull('assigned_user_id')
                ->whereHas('tickets', fn ($ticketQuery) => $ticketQuery
                    ->where('type', ServiceTicket::TYPE_ESCALATION)
                    ->where('status', ServiceTicket::STATUS_OPEN)),
            self::INBOX_GROUP_MINE => $query->where('assigned_user_id', $actorId ?? 0),
            self::INBOX_GROUP_AI => $query
                ->whereNull('assigned_user_id')
                ->whereDoesntHave('tickets', $activeEscalation),
            self::INBOX_GROUP_SENDS => $query->whereSilentCampaignSend(),
            default => $query,
        };
    }

    /**
     * Conversations where the customer spoke last and nobody has answered.
     *
     * This is what the inbox tab badges count. There is no per-operator read state in the
     * system — no table records who opened which conversation — so "unread" is defined by
     * the conversation itself rather than by a viewer: the customer wrote, and neither a
     * human nor the agent has replied since. It clears the moment anything goes out, which
     * is the behaviour an operator actually wants from a badge: it means work is waiting,
     * not that a row exists.
     */
    public function scopeAwaitingReply($query): Builder
    {
        return $query
            ->whereNotNull('last_inbound_at')
            ->where(fn ($q) => $q
                ->whereNull('last_outbound_at')
                ->orWhereColumn('last_inbound_at', '>', 'last_outbound_at'));
    }

    /**
     * A lead a campaign created that has never sent anything back.
     *
     * last_inbound_at is the discriminator rather than a dedicated flag: it is already
     * stamped on the first inbound message, so a lead crosses out of this set by itself
     * the moment the recipient replies — nothing has to remember to clear a marker.
     */
    public function scopeWhereSilentCampaignSend($query): Builder
    {
        return $query->whereNull('last_inbound_at')->whereNotNull('campaign_id');
    }

    /**
     * Drop campaign sends nobody answered from a lead population.
     *
     * For any metric that reads leads as demand — how many arrived, what share converted —
     * counting them lies twice: the total inflates by the size of the fan-out, and every
     * rate computed against it collapses by dilution without anything having got worse.
     */
    public function scopeWithoutSilentCampaignSends($query): Builder
    {
        return $query->where(fn ($q) => $q->whereNotNull('last_inbound_at')->orWhereNull('campaign_id'));
    }

    /**
     * Apply the inbox filter set (group / status / ai_mode / operational_stage /
     * assignment / free-text search) and the sort. The instance filter and
     * visibility restriction are applied by the caller; this scope owns only the
     * validated filter payload.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeInboxFiltered($query, array $filters): Builder
    {
        $group = $filters['group'] ?? self::INBOX_GROUP_ALL;

        $query->inGroup($group, auth()->id());

        // Load-bearing: without this an unanswered campaign send still satisfies "todas"
        // and "ia" (unassigned, no escalation), so a large fan-out buries the real queue
        // and the segregation buys nothing. Every tab but "envios" subtracts them.
        if ($group !== self::INBOX_GROUP_SENDS) {
            $query->withoutSilentCampaignSends();
        }

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

        return $query->orderByInbox($filters['sort'] ?? null, $filters['direction'] ?? 'desc');
    }

    /**
     * The inbox order, with the two ways a NULL used to break it closed.
     *
     * Postgres sorts NULLs FIRST on DESC, so every lead that had never been touched —
     * a manual test send, an import nobody answered — pinned itself above the live
     * conversations and stayed there. SQLite orders them the opposite way, which is why
     * the whole test suite agreed the ordering was fine.
     *
     * last_interaction_at falls back to created_at rather than merely sinking, because
     * that is already what the row label shows (ConversationInboxPropsBuilder builds
     * `ultima_interacao` the same way): a row reading "há 6 dias" has to sort as six days
     * old, not as an absence. NULLS LAST covers the remaining sortable columns, where
     * there is no meaningful fallback and an empty value simply belongs at the bottom.
     */
    public function scopeOrderByInbox($query, ?string $sort, string $direction): Builder
    {
        $sort = in_array($sort, self::INBOX_SORT_COLUMNS, true) ? $sort : 'last_interaction_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $column = $sort === 'last_interaction_at'
            ? 'COALESCE(leads.last_interaction_at, leads.created_at)'
            : 'leads.'.$sort;

        return $query->orderByRaw("{$column} {$direction} NULLS LAST");
    }

    protected $casts = [
        'credito_json' => 'array',
        'documentos_coletados' => 'array',
        'last_interaction_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
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

        CustomFieldValue::updateOrCreate(
            ['custom_field_id' => $field->id, 'entity_type' => 'lead', 'entity_id' => $this->id],
            [$field->valueColumn() => $value]
        );
    }
}
