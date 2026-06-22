<?php

namespace App\Models;

use App\Support\PromptLayerCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolDefinition extends Model
{
    protected static function booted(): void
    {
        static::saved(fn (self $tool) => PromptLayerCache::bump((string) $tool->tenant_id));
        static::deleted(fn (self $tool) => PromptLayerCache::bump((string) $tool->tenant_id));
    }

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'slug',
        'name',
        'description',
        'type',
        'config',
        'schema',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForAgent(Builder $query, ?int $agentId): Builder
    {
        return $query->where(function (Builder $q) use ($agentId) {
            $q->where('agent_id', $agentId)->orWhereNull('agent_id');
        });
    }
}
