<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoiceCampaign extends Model
{
    /** @use HasFactory<\Database\Factories\VoiceCampaignFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'contact_list_id',
        'voice_instance_id',
        'greeting_template',
        'tts_voice',
        'dtmf_actions',
        'post_call_message',
        'status',
        'delay_between_calls_ms',
        'started_at',
        'completed_at',
        'paused_at',
        'total_calls',
        'total_answered',
        'total_interested',
        'total_no_answer',
        'total_failed',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'dtmf_actions' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime',
            'total_calls' => 'integer',
            'total_answered' => 'integer',
            'total_interested' => 'integer',
            'total_no_answer' => 'integer',
            'total_failed' => 'integer',
            'delay_between_calls_ms' => 'integer',
        ];
    }

    /**
     * Supported DTMF actions with their behavior labels.
     *
     * @return array<string, string>
     */
    public static function availableDtmfActions(): array
    {
        return [
            'interested' => 'Tenho interesse — notificar agente via WhatsApp',
            'optout' => 'Não quero mais receber ligações',
            'callback' => 'Me ligue mais tarde',
            'hangup' => 'Encerrar chamada silenciosamente',
        ];
    }

    /**
     * Returns the campaign's dtmf_actions or an empty array.
     * Each entry: ['action' => string, 'label' => string]
     *
     * @return array<string, array{action: string, label: string}>
     */
    public function resolvedDtmfActions(): array
    {
        return $this->dtmf_actions ?? [];
    }

    /**
     * Returns true if at least one DTMF action is configured.
     */
    public function hasDtmfConfigured(): bool
    {
        return ! empty($this->dtmf_actions);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function voiceInstance(): BelongsTo
    {
        return $this->belongsTo(VoiceInstance::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(VoiceCampaignCall::class);
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
        return in_array($this->status, ['draft']);
    }

    public function canPause(): bool
    {
        return $this->status === 'sending';
    }

    public function canResume(): bool
    {
        return $this->status === 'paused';
    }

    public function incrementCounter(string $field): void
    {
        $this->increment($field);
    }

    public function interestRate(): float
    {
        if ($this->total_answered <= 0) {
            return 0.0;
        }

        return round(($this->total_interested / $this->total_answered) * 100, 2);
    }

    public function answerRate(): float
    {
        if ($this->total_calls <= 0) {
            return 0.0;
        }

        return round(($this->total_answered / $this->total_calls) * 100, 2);
    }
}
