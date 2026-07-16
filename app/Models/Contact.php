<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contact is the canonical CRM identity (phone-as-key) — purely descriptive.
 *
 * Tags live on Lead (D1, Phase 47.1). Contact intentionally does NOT include
 * the HasTags trait: tags are a sales-funnel concern, not a profile attribute.
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_LEAD_SYNC = 'lead_sync';

    public const SOURCE_CSV_IMPORT = 'csv_import';

    public const SOURCE_WHATSAPP_INBOUND = 'whatsapp_inbound';

    public const SOURCE_WHATSAPP_APP_SYNC = 'whatsapp_app_sync';

    public const SOURCE_URA = 'ura';

    public const SOURCE_AGENT_API = 'agent_api';

    public const OPT_PENDING = 'pending';

    public const OPT_IN = 'opted_in';

    public const OPT_OUT = 'opted_out';

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'cpf',
        'source',
        'opt_in_status',
        'opt_in_at',
        'opt_out_at',
        'extra_data',
        'notes',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'extra_data' => 'array',
            'opt_in_at' => 'datetime',
            'opt_out_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function contactListEntries(): HasMany
    {
        return $this->hasMany(ContactListEntry::class);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like, $digits): void {
            $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like);

            if ($digits !== '') {
                $q->orWhere('phone', 'like', '%'.$digits.'%')
                    ->orWhere('cpf', 'like', '%'.$digits.'%');
            }
        });
    }

    public function markOptedIn(): void
    {
        $this->update([
            'opt_in_status' => self::OPT_IN,
            'opt_in_at' => now(),
            'opt_out_at' => null,
        ]);
    }

    public function markOptedOut(): void
    {
        $this->update([
            'opt_in_status' => self::OPT_OUT,
            'opt_out_at' => now(),
        ]);
    }
}
