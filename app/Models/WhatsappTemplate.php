<?php

namespace App\Models;

use App\Enums\TemplateKind;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WhatsappTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class WhatsappTemplate extends Model
{
    /** @use HasFactory<WhatsappTemplateFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // SCALE-4: a template status change (APPROVED → revoked/paused via the Meta sync) or
        // soft-delete must invalidate the bulk send-config cache immediately, so a campaign
        // can never keep sending against a now-unapproved template within a TTL window.
        static::saved(fn (self $template) => Cache::forget("whatsapp_send_template:{$template->id}"));
        static::deleted(fn (self $template) => Cache::forget("whatsapp_send_template:{$template->id}"));
    }

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

    public function scopeOfKind(Builder $query, TemplateKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    /**
     * Highest {{n}} placeholder index in a template body. Returns 0 when none.
     */
    public static function countVariablesIn(string $text): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', $text, $matches);

        if (empty($matches[1])) {
            return 0;
        }

        return (int) max(array_map('intval', $matches[1]));
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
