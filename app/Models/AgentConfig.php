<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AgentConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class AgentConfig extends Model
{
    /** @use HasFactory<AgentConfigFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * AgentConfigResolver caches the resolved row under agent_config_id_{agent_id}
     * (300s TTL). Bust it on every write path — controllers used to forget it
     * manually, which left gaps (e.g. template apply, seeding, console edits).
     */
    protected static function booted(): void
    {
        static::saved(fn (self $config) => Cache::forget("agent_config_id_{$config->agent_id}"));
        static::deleted(fn (self $config) => Cache::forget("agent_config_id_{$config->agent_id}"));
    }

    protected $fillable = [
        'agent_id',
        'tenant_id',
        'agent_niche',
        'template_slug',
        'tool_capabilities',
        'agent_name',
        'company_name',
        'agent_personality',
        'max_chars',
        'agent_greeting',
        'required_docs',
        'extra_rules',
        'agent_provider',
        'agent_model',
        'transcription_provider',
        'transcription_model',
        'vision_provider',
        'vision_model',
        'temperature',
        'max_tokens',
        'max_conversation_messages',
        'followup_first_delay_minutes',
        'followup_daily_time',
        'followup_max_count',
        'followup_approach',
        'followup_window_start',
        'followup_window_end',
        'followup_interval_days',
        'followup_message_type',
        'followup_tone',
        'followup_persuasion_intensity',
        'followup_custom_instructions',
    ];

    protected $casts = [
        'tool_capabilities' => 'array',
        'max_chars' => 'integer',
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'max_conversation_messages' => 'integer',
        'followup_first_delay_minutes' => 'integer',
        'followup_max_count' => 'integer',
        'followup_interval_days' => 'integer',
        'followup_persuasion_intensity' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
