<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowupMessage extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'lead_id',
        'tenant_id',
        'attempt',
        'message_text',
        'tone',
        'sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
