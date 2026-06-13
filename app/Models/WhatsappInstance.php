<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class WhatsappInstance extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappInstanceFactory> */
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
    ];

    protected function casts(): array
    {
        return [
            'provider' => \App\Enums\WhatsAppProvider::class,
            'meta_access_token' => 'encrypted',
            'meta_token_permanent' => 'boolean',
            'meta_token_expires_at' => 'datetime',
            'meta_coexistence' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $instance) => Cache::forget("agent_context_instance_{$instance->name}"));

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
}
