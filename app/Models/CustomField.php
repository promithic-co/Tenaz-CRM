<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'slug',
        'label',
        'type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * The `custom_field_values` column that stores this field's type.
     *
     * Values are typed per column rather than serialized into one, so the mapping
     * lives here — on the model that owns `type` — instead of being restated by
     * every reader and writer.
     */
    public function valueColumn(): string
    {
        return self::valueColumnFor((string) $this->type);
    }

    public static function valueColumnFor(string $type): string
    {
        return match ($type) {
            'number' => 'value_number',
            'json' => 'value_json',
            'date' => 'value_date',
            'boolean' => 'value_boolean',
            default => 'value_text',
        };
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEntity(Builder $query, string $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }
}
