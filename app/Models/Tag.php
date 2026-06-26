<?php

namespace App\Models;

use App\Exceptions\Tag\TagLimitReachedException;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Tenant-scoped polymorphic tag.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property int|null $created_by
 * @property int $usage_count
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const COLORS = [
        'gray',
        'red',
        'orange',
        'yellow',
        'green',
        'blue',
        'purple',
        'pink',
    ];

    /**
     * Hard cap on tags per tenant (D5 — Phase 47.1).
     *
     * Applies to controller creation AND auto-tag IA paths via
     * `findOrCreateBySlug`. Excess tags created before the cap was
     * introduced coexist; the cap only blocks new inserts.
     */
    public const MAX_PER_TENANT = 50;

    /**
     * Only user-modifiable fields are mass-assignable. Internal columns
     * (tenant_id, usage_count, created_by) must be set via forceFill or
     * direct attribute assignment to prevent privilege-escalation if a
     * future controller naively passes $request->all() into Tag::create().
     */
    protected $fillable = [
        'name',
        'slug',
        'color',
        'is_hot',
        'ai_detectable',
        'ai_description',
        'ai_min_confidence',
    ];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
            'is_hot' => 'boolean',
            'ai_detectable' => 'boolean',
            'ai_min_confidence' => 'decimal:2',
        ];
    }

    /**
     * Setting the name auto-derives the slug when no explicit slug was set.
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;
        if (empty($this->attributes['slug'] ?? null)) {
            $this->attributes['slug'] = Str::slug($value);
        }
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

        $like = '%'.Str::lower($term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('slug', 'like', $like)
                ->orWhere('name', 'like', $like);
        });
    }

    /**
     * Create a new Tag bound to the given tenant. Internal fields
     * (tenant_id, created_by, usage_count) are not mass-assignable so they
     * are set via forceFill here. Prefer this over Tag::create() in code
     * that owns the tenant id.
     *
     * @param  array<string, mixed>  $attrs
     */
    public static function createForTenant(string $tenantId, array $attrs, ?int $createdBy = null): Tag
    {
        $name = (string) ($attrs['name'] ?? '');

        $tag = (new self)->forceFill([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $attrs['slug'] ?? Str::slug($name),
            'color' => $attrs['color'] ?? 'gray',
            'is_hot' => (bool) ($attrs['is_hot'] ?? false),
            'ai_detectable' => (bool) ($attrs['ai_detectable'] ?? false),
            'ai_description' => $attrs['ai_description'] ?? null,
            'ai_min_confidence' => $attrs['ai_min_confidence'] ?? 0.70,
            'created_by' => $createdBy,
            'usage_count' => $attrs['usage_count'] ?? 0,
        ]);
        $tag->save();

        return $tag;
    }

    /**
     * Find an existing tag by slug for the given tenant or create a new one.
     *
     * If a matching tag is soft-deleted, it is restored and returned. The
     * unique index on (tenant_id, slug) covers trashed rows, so we must
     * explicitly include them and restore rather than letting Tag::create
     * blow up with a QueryException.
     */
    public static function findOrCreateBySlug(
        string $tenantId,
        string $name,
        ?int $userId = null,
        string $color = 'gray',
    ): Tag {
        $slug = Str::slug($name);

        $existing = static::query()
            ->withoutGlobalScope('tenant')
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        // Cap applies to AI / import / system callers that come through
        // findOrCreateBySlug — same ceiling as the human-facing controller
        // store path. Soft-deleted tags are not counted (deleted_at filter).
        $activeCount = static::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->count();

        if ($activeCount >= self::MAX_PER_TENANT) {
            throw new TagLimitReachedException(
                "Tenant {$tenantId} reached the ".self::MAX_PER_TENANT.'-tag limit.',
            );
        }

        $tag = (new self)->forceFill([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'color' => in_array($color, self::COLORS, true) ? $color : 'gray',
            'created_by' => $userId,
        ]);
        $tag->save();

        return $tag;
    }

    /**
     * @return MorphToMany<Lead>|MorphToMany<Contact>
     */
    public function taggables(string $type): MorphToMany
    {
        return $this->morphedByMany($type, 'taggable')
            ->withPivot('source', 'tagged_by', 'ai_confidence', 'ai_evidence', 'ai_evaluated_at')
            ->withTimestamps();
    }
}
