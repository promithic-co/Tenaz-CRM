<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactListEntry extends Model
{
    /** @use HasFactory<\Database\Factories\ContactListEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_list_id',
        'phone',
        'name',
        'opt_in_status',
        'opt_in_at',
        'opt_out_at',
        'lead_id',
        'contact_id',
        'extra_data',
    ];

    protected function casts(): array
    {
        return [
            'extra_data' => 'array',
            'opt_in_at' => 'datetime',
            'opt_out_at' => 'datetime',
        ];
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function scopeOptedIn(Builder $query): Builder
    {
        return $query->where('opt_in_status', 'opted_in');
    }

    public function markOptedIn(): void
    {
        $this->update([
            'opt_in_status' => 'opted_in',
            'opt_in_at' => now(),
            'opt_out_at' => null,
        ]);
    }

    public function markOptedOut(): void
    {
        $this->update([
            'opt_in_status' => 'opted_out',
            'opt_out_at' => now(),
        ]);
    }
}
