<?php

namespace App\Models;

use App\Enums\TemplateKind;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappTemplateFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'whatsapp_instance_id',
        'kind',
        'meta_template_id',
        'meta_template_name',
        'meta_waba_id',
        'element_name',
        'name',
        'status',
        'category',
        'language',
        'body',
        'header',
        'footer',
        'buttons_json',
        'components_json',
        'quality_score',
        'rejected_reason',
        'variables_count',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TemplateKind::class,
            'buttons_json' => 'array',
            'components_json' => 'array',
            'last_synced_at' => 'datetime',
            'variables_count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function whatsappInstance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeForInstance(Builder $query, int $instanceId): Builder
    {
        return $query->where('whatsapp_instance_id', $instanceId);
    }

    public function scopeOfKind(Builder $query, TemplateKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    /** @return list<string> */
    public function variableNames(): array
    {
        if ($this->variables_count <= 0) {
            return [];
        }

        return array_map(fn (int $i) => (string) $i, range(1, $this->variables_count));
    }
}
