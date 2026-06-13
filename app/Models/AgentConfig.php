<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentConfig extends Model
{
    /** @use HasFactory<\Database\Factories\AgentConfigFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'agent_id',
        'tenant_id',
        'agent_niche',
        'template_slug',
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
