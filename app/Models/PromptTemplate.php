<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'agent_id',
        'name',
        'slug',
        'type',
        'content',
        'version',
        'is_active',
        'variables_schema',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
            'variables_schema' => 'array',
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

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForAgent(Builder $query, ?int $agentId): Builder
    {
        return $query->where(function (Builder $q) use ($agentId) {
            $q->where('agent_id', $agentId)->orWhereNull('agent_id');
        })->orderByRaw('agent_id IS NULL ASC');
    }

    /**
     * Render template content by substituting {{variable}} placeholders.
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(array $variables): string
    {
        $content = $this->content;

        foreach ($variables as $key => $value) {
            $content = str_replace('{{'.$key.'}}', (string) $value, $content);
        }

        return $content;
    }

    /**
     * Save a new version of this template, deactivating the current one.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveNewVersion(array $data): static
    {
        $this->update(['is_active' => false]);

        return static::create(array_merge([
            'tenant_id' => $this->tenant_id,
            'agent_id' => $this->agent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'version' => $this->version + 1,
            'is_active' => true,
        ], $data));
    }
}
