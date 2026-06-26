<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ContactListFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactList extends Model
{
    /** @use HasFactory<ContactListFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'source',
        'is_dynamic',
        'filters_json',
        'entries_count',
        'last_resolved_count',
        'last_resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_dynamic' => 'bool',
            'filters_json' => 'array',
            'entries_count' => 'integer',
            'last_resolved_count' => 'integer',
            'last_resolved_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ContactListEntry::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function refreshEntriesCount(): void
    {
        $this->update(['entries_count' => $this->entries()->count()]);
    }
}
