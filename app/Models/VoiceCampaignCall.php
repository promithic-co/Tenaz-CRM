<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceCampaignCall extends Model
{
    /** @use HasFactory<\Database\Factories\VoiceCampaignCallFactory> */
    use HasFactory;

    protected $fillable = [
        'voice_campaign_id',
        'contact_list_entry_id',
        'phone',
        'contact_name',
        'interpolated_message',
        'call_sid',
        'status',
        'called_at',
        'answered_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
            'answered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function voiceCampaign(): BelongsTo
    {
        return $this->belongsTo(VoiceCampaign::class);
    }

    public function contactListEntry(): BelongsTo
    {
        return $this->belongsTo(ContactListEntry::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeInterested(Builder $query): Builder
    {
        return $query->where('status', 'interested');
    }

    public function scopeAnswered(Builder $query): Builder
    {
        return $query->where('status', 'answered');
    }
}
