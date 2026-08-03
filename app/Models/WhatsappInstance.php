<?php

namespace App\Models;

use App\Enums\MetaHealthStatus;
use App\Enums\WhatsAppProvider;
use App\Models\Concerns\BelongsToTenant;
use App\Services\WhatsApp\MetaAccountHealthWebhookService;
use Database\Factories\WhatsappInstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class WhatsappInstance extends Model
{
    /** @use HasFactory<WhatsappInstanceFactory> */
    use BelongsToTenant, HasFactory;

    public const AI_MODE_AUTOMATIC = Lead::AI_MODE_AUTOMATIC;

    public const AI_MODE_MANUAL = Lead::AI_MODE_MANUAL;

    public const AI_MODE_ASSISTED = Lead::AI_MODE_ASSISTED;

    public const AI_MODE_QUALIFY_THEN_HANDOFF = Lead::AI_MODE_QUALIFY_THEN_HANDOFF;

    public const AI_MODES = Lead::AI_MODES;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'display_name',
        'phone_number',
        'agent_id',
        'default_ai_mode',
        'api_url',
        'api_key',
        'provider',
        'proxy_host',
        'proxy_port',
        'proxy_protocol',
        'proxy_username',
        'proxy_password',
        'meta_phone_number_id',
        'meta_waba_id',
        'meta_access_token',
        'meta_system_user_id',
        'meta_token_permanent',
        'meta_token_expires_at',
        'meta_quality_rating',
        'meta_coexistence',
        'meta_health_status',
        'meta_health_entities',
        'meta_name_status',
        'meta_code_verification_status',
        'meta_portfolio_messaging_limit',
        'meta_throughput_level',
        'meta_number_status',
        'meta_account_restrictions',
        'meta_ban_state',
        'meta_account_review_status',
        'meta_health_checked_at',
    ];

    /**
     * FE-SEC-01: never serialize instance secrets into Inertia props / JSON.
     * meta_access_token is cast `encrypted`, so array/JSON serialization would
     * otherwise emit it DECRYPTED. Backend send paths read these as properties,
     * which $hidden does not affect.
     *
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
        'meta_access_token',
        'proxy_password',
        'proxy_username',
        'proxy_host',
        'proxy_port',
        'api_url',
    ];

    protected function casts(): array
    {
        return [
            'provider' => WhatsAppProvider::class,
            'meta_access_token' => 'encrypted',
            'meta_token_permanent' => 'boolean',
            'meta_token_expires_at' => 'datetime',
            'meta_coexistence' => 'boolean',
            'meta_health_status' => MetaHealthStatus::class,
            'meta_health_entities' => 'array',
            'meta_account_restrictions' => 'array',
            'meta_health_checked_at' => 'datetime',
        ];
    }

    /**
     * Meta's messaging health for this number. Falls back to `Unknown` while the
     * column is still null (instance created before the first health sync).
     */
    public function healthStatus(): MetaHealthStatus
    {
        return $this->meta_health_status instanceof MetaHealthStatus
            ? $this->meta_health_status
            : MetaHealthStatus::Unknown;
    }

    /**
     * Human-readable reasons Meta gave for a LIMITED or BLOCKED status, flattened
     * across every entity (phone number, WABA, business portfolio, app).
     *
     * @return list<string>
     */
    public function healthReasons(): array
    {
        $reasons = [];

        foreach ((array) ($this->meta_health_entities ?? []) as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            foreach ((array) ($entity['reasons'] ?? []) as $reason) {
                if (is_string($reason) && trim($reason) !== '') {
                    $reasons[] = trim($reason);
                }
            }
        }

        return array_values(array_unique($reasons));
    }

    protected static function booted(): void
    {
        static::saved(function (self $instance): void {
            Cache::forget("agent_context_instance_{$instance->name}");
            // SCALE-4: bust the campaign send-config cache the instant a token rotates or
            // any instance field changes, so the bulk send path never uses a stale instance.
            Cache::forget("whatsapp_send_instance:{$instance->id}");
        });

        static::deleted(fn (self $instance) => Cache::forget("whatsapp_send_instance:{$instance->id}"));

        // Enforce 1 WABA = 1 agent: all whatsapp_instances rows sharing meta_waba_id
        // must share the same agent_id. Catches accidental cross-WABA reassignment.
        static::saving(function (self $instance): void {
            if (empty($instance->meta_waba_id) || empty($instance->agent_id)) {
                return;
            }

            $conflict = static::query()
                ->withoutGlobalScopes()
                ->where('meta_waba_id', $instance->meta_waba_id)
                ->where('agent_id', '!=', $instance->agent_id)
                ->when($instance->exists, fn ($q) => $q->where('id', '!=', $instance->id))
                ->exists();

            if ($conflict) {
                throw new \DomainException(
                    "meta_waba_id {$instance->meta_waba_id} is already attached to a different agent; one WABA may only be linked to a single agent."
                );
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** Label amigável para exibição (display_name ou name). */
    public function label(): string
    {
        return $this->display_name ?: $this->name;
    }

    /** Indica se esta instância tem proxy residencial configurado. */
    public function hasProxy(): bool
    {
        return filled($this->proxy_host) && filled($this->proxy_port);
    }

    /**
     * Restriction types Meta has placed on this WABA that stop business-initiated
     * messaging, ignoring any whose expiry has already passed.
     *
     * @return list<string>
     */
    public function activeMessagingRestrictions(): array
    {
        $restrictions = (array) (($this->meta_account_restrictions ?? [])['restrictions'] ?? []);
        $active = [];

        foreach ($restrictions as $restriction) {
            if (! is_array($restriction)) {
                continue;
            }

            $type = strtoupper((string) ($restriction['type'] ?? ''));

            if (! in_array($type, MetaAccountHealthWebhookService::MESSAGING_RESTRICTIONS, true)) {
                continue;
            }

            $expiresAt = $restriction['expires_at'] ?? null;

            if (is_string($expiresAt) && $expiresAt !== '' && Carbon::parse($expiresAt)->isPast()) {
                continue;
            }

            $active[] = $type;
        }

        return array_values(array_unique($active));
    }

    public function hasExpiredMetaToken(): bool
    {
        return ! $this->meta_token_permanent
            && $this->meta_token_expires_at !== null
            && $this->meta_token_expires_at->isPast();
    }
}
