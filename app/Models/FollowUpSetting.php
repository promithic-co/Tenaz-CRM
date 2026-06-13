<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FollowUpSetting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'followup_settings';

    protected $fillable = [
        'tenant_id',
        'enabled',
        'first_delay_minutes',
        'min_interval_minutes',
        'max_attempts_within_window',
        'business_window_start',
        'business_window_end',
        'timezone',
        'message_type',
        'tone',
        'persuasion_intensity',
        'custom_instructions',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'first_delay_minutes' => 'integer',
        'min_interval_minutes' => 'integer',
        'max_attempts_within_window' => 'integer',
        'persuasion_intensity' => 'integer',
    ];
}
