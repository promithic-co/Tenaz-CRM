<?php

namespace App\Models\Concerns;

use App\Enums\TaggableSource;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provides polymorphic tagging behavior to a model. Tags are tenant-scoped via
 * the Tag model. Helpers do not assume a global scope is active on Tag, so they
 * work from console + jobs by resolving by id.
 *
 * Source semantics (D2 — locked):
 * - Manual = ground truth. Humans (HTTP requests) attach/detach freely.
 * - Ai = enrichment only. AI callers MUST pass TaggableSource::Ai so the trait
 *   can guarantee they never overwrite Manual links.
 * - Import / System = bulk feeds and server-side automations.
 *
 * IA callers (Phase 50 jobs) MUST use TaggableSource::Ai when calling
 * attachTag/syncTags/detachTag — this is what lets the trait scope mutations
 * to AI-created pivot rows only.
 */
trait HasTags
{
    /**
     * @return MorphToMany<Tag>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->withPivot('source', 'tagged_by', 'ai_confidence', 'ai_evidence', 'ai_evaluated_at')
            ->withTimestamps();
    }

    /**
     * Attach a single tag by Tag instance or by slug (auto-creates if missing).
     *
     * Wrapped in a transaction so the pivot row and the usage_count increment
     * commit atomically. The exists() check is best-effort against duplicate
     * attaches; the (tag_id, taggable_type, taggable_id) unique index in the
     * taggables table is the real guarantee. Concurrent duplicate attaches
     * will throw QueryException which we recover from cleanly.
     *
     * @param  array<string, mixed>  $metadata  Optional AI pivot fields (ai_confidence, ai_evidence, ai_evaluated_at).
     */
    public function attachTag(Tag|string|int $tag, TaggableSource $source = TaggableSource::Manual, ?int $userId = null, array $metadata = []): void
    {
        $resolved = $this->resolveTag($tag, $userId);

        DB::transaction(function () use ($resolved, $source, $userId, $metadata): void {
            if ($this->tags()->where('tags.id', $resolved->id)->exists()) {
                return;
            }

            $pivotData = array_merge([
                'source' => $source->value,
                'tagged_by' => $userId,
            ], $metadata);

            try {
                $this->tags()->attach($resolved->id, $pivotData);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Concurrent attach won the race; the pivot row already exists.
                return;
            }

            Tag::query()->whereKey($resolved->id)->increment('usage_count');
        });
    }

    /**
     * Detach a tag by Tag instance or by slug.
     *
     * Source scoping:
     * - $callerSource === null → unconditional detach (legacy behavior, used by
     *   tests and admin tooling that knows what it's doing).
     * - $callerSource === TaggableSource::Ai → only detaches pivots whose
     *   source is also 'ai'. Manual tags are immutable to the AI (D2).
     * - $callerSource === TaggableSource::Manual → human override. Detaches
     *   regardless of pivot source — the human can always take a tag off.
     * - Import / System behave like Manual (server-side authority).
     */
    public function detachTag(Tag|string $tag, ?TaggableSource $callerSource = null): void
    {
        $resolved = $tag instanceof Tag
            ? $tag
            : Tag::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $this->getTagTenantId())
                ->where('slug', Str::slug($tag))
                ->first();

        if (! $resolved) {
            return;
        }

        DB::transaction(function () use ($resolved, $callerSource): void {
            $relation = $this->tags();

            if ($callerSource === TaggableSource::Ai) {
                $relation = $relation->wherePivot('source', TaggableSource::Ai->value);
            }

            $detached = $relation->detach($resolved->id);

            if ($detached > 0) {
                Tag::query()
                    ->whereKey($resolved->id)
                    ->where('usage_count', '>', 0)
                    ->decrement('usage_count');
            }
        });
    }

    /**
     * Sync tags by id, replacing any existing tags. Updates usage counts accordingly.
     *
     * When $source === TaggableSource::Ai the sync is scoped to AI pivots only:
     * existing manual / import / system pivots are NEVER touched. This honors
     * D2 (IA does not overwrite human ground truth).
     *
     * Manual / Import / System callers do a full replacement (legacy behavior).
     *
     * @param  array<int>  $tagIds
     */
    public function syncTags(array $tagIds, TaggableSource $source = TaggableSource::Manual, ?int $userId = null): void
    {
        if ($source === TaggableSource::Ai) {
            $this->syncAiTags($tagIds, $userId);

            return;
        }

        DB::transaction(function () use ($tagIds, $source, $userId): void {
            $existing = $this->tags()->pluck('tags.id')->all();

            $toAttach = array_diff($tagIds, $existing);
            $toDetach = array_diff($existing, $tagIds);

            foreach ($toDetach as $id) {
                $this->tags()->detach($id);
                Tag::query()->whereKey($id)->where('usage_count', '>', 0)->decrement('usage_count');
            }

            foreach ($toAttach as $id) {
                try {
                    $this->tags()->attach($id, [
                        'source' => $source->value,
                        'tagged_by' => $userId,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    continue;
                }
                Tag::query()->whereKey($id)->increment('usage_count');
            }
        });
    }

    /**
     * AI sync: only reconciles tag pivots whose source is 'ai'. Manual
     * (and import/system) pivots stay intact.
     *
     * @param  array<int>  $tagIds
     */
    private function syncAiTags(array $tagIds, ?int $userId): void
    {
        DB::transaction(function () use ($tagIds, $userId): void {
            $existingAi = $this->tags()
                ->wherePivot('source', TaggableSource::Ai->value)
                ->pluck('tags.id')
                ->all();

            $toAttach = array_diff($tagIds, $existingAi);
            $toDetach = array_diff($existingAi, $tagIds);

            foreach ($toDetach as $id) {
                $this->tags()
                    ->wherePivot('source', TaggableSource::Ai->value)
                    ->detach($id);
                Tag::query()->whereKey($id)->where('usage_count', '>', 0)->decrement('usage_count');
            }

            foreach ($toAttach as $id) {
                // Skip if any pivot (manual or ai) already exists for this tag.
                // attachTag is idempotent and will respect that.
                $this->attachTag($id, TaggableSource::Ai, $userId);
            }
        });
    }

    public function hasTag(string $slug): bool
    {
        return $this->tags()->where('slug', $slug)->exists();
    }

    private function resolveTag(Tag|string|int $tag, ?int $userId): Tag
    {
        if ($tag instanceof Tag) {
            return $tag;
        }

        if (is_int($tag)) {
            $found = Tag::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $this->getTagTenantId())
                ->whereKey($tag)
                ->first();

            if ($found) {
                return $found;
            }

            throw new \RuntimeException("Tag id {$tag} not found in tenant scope.");
        }

        return Tag::findOrCreateBySlug(
            tenantId: $this->getTagTenantId(),
            name: $tag,
            userId: $userId,
        );
    }

    /**
     * Resolve the tenant id for tag operations. Defaults to the consuming
     * model's tenant_id (which works for any model using BelongsToTenant).
     *
     * Override in the consuming model if it does not expose a tenant_id
     * column. Falls back to the authenticated user's tenant when neither
     * is available, throwing a LogicException only when no tenant context
     * can be resolved (e.g., a console command on a non-tenanted model).
     */
    protected function getTagTenantId(): string
    {
        if (isset($this->attributes['tenant_id']) && $this->attributes['tenant_id'] !== null) {
            return (string) $this->attributes['tenant_id'];
        }

        $authUser = auth()->user();
        if ($authUser && isset($authUser->tenantId)) {
            return (string) $authUser->tenantId;
        }

        throw new \LogicException(
            'HasTags requires a tenant_id on the consuming model or an authenticated user with a tenantId. '.
            'Override getTagTenantId() in '.static::class.' to provide one.',
        );
    }
}
