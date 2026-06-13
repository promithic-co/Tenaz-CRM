<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    /** @use HasFactory<\Database\Factories\AgentFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'slug',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): HasOne
    {
        return $this->hasOne(AgentConfig::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function whatsappInstance(): HasOne
    {
        return $this->hasOne(WhatsappInstance::class);
    }
}
