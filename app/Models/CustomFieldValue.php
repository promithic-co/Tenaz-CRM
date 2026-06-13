<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_field_id',
        'entity_type',
        'entity_id',
        'value_text',
        'value_number',
        'value_json',
        'value_date',
        'value_boolean',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_json' => 'array',
            'value_date' => 'date',
            'value_boolean' => 'boolean',
        ];
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    /** Get the typed value based on the field's type. */
    public function getValue(): mixed
    {
        return match ($this->customField->type ?? 'text') {
            'text', 'select' => $this->value_text,
            'number' => $this->value_number,
            'json' => $this->value_json,
            'date' => $this->value_date,
            'boolean' => $this->value_boolean,
            default => $this->value_text,
        };
    }
}
