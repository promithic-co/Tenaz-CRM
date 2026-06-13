<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * REST controller for tenant-scoped Tag CRUD. All listing is filtered by the
 * authenticated tenant via the BelongsToTenant global scope on Tag.
 */
class TagController extends Controller
{
    /**
     * List tags for the current tenant. Supports ?q= fuzzy search and ?popular=1 ordering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query()->forTenant((string) $request->user()->tenantId);

        if ($term = $request->query('q')) {
            $query->search((string) $term);
        }

        if ($request->boolean('popular')) {
            $query->orderByDesc('usage_count');
        } else {
            $query->orderBy('name');
        }

        $tags = $query->paginate(25)->withQueryString();

        return response()->json($tags);
    }

    /**
     * Create a new tag in the current tenant.
     *
     * The Form Request validates slug uniqueness before insert, but that
     * check is a TOCTOU window. The (tenant_id, slug) unique index is the
     * source of truth — catch its violation and surface a 422 instead of
     * letting it bubble as a 500.
     */
    public function store(StoreTagRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = (string) $request->user()->tenantId;

        $existing = Tag::query()->forTenant($tenantId)->count();
        if ($existing >= Tag::MAX_PER_TENANT) {
            throw ValidationException::withMessages([
                'name' => 'Limite de '.Tag::MAX_PER_TENANT.' tags por organização atingido. Remova tags não utilizadas.',
            ]);
        }

        try {
            $tag = (new Tag)->forceFill([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'color' => $data['color'] ?? 'gray',
                'is_hot' => (bool) ($data['is_hot'] ?? false),
                'ai_detectable' => (bool) ($data['ai_detectable'] ?? false),
                'ai_description' => $data['ai_description'] ?? null,
                'ai_min_confidence' => $data['ai_min_confidence'] ?? 0.70,
                'created_by' => $request->user()->id,
            ]);
            $tag->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'slug' => 'Já existe uma tag com este nome neste tenant.',
            ]);
        }

        return response()->json($tag, 201);
    }

    /**
     * Rename / recolor a tag. Slug re-derives from new name.
     */
    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $data = $request->validated();

        try {
            $update = [
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'color' => $data['color'] ?? $tag->color,
            ];
            if (array_key_exists('is_hot', $data)) {
                $update['is_hot'] = (bool) $data['is_hot'];
            }
            if (array_key_exists('ai_detectable', $data)) {
                $update['ai_detectable'] = (bool) $data['ai_detectable'];
            }
            if (array_key_exists('ai_description', $data)) {
                $update['ai_description'] = $data['ai_description'];
            }
            if (array_key_exists('ai_min_confidence', $data)) {
                $update['ai_min_confidence'] = $data['ai_min_confidence'];
            }
            $tag->update($update);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'slug' => 'Já existe uma tag com este nome neste tenant.',
            ]);
        }

        return response()->json($tag->fresh());
    }

    /**
     * Soft-delete a tag. Pivot rows persist; usage_count not decremented because
     * the tag is gone and any future restore retains attachments.
     */
    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(['ok' => true]);
    }
}
