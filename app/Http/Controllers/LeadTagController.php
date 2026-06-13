<?php

namespace App\Http\Controllers;

use App\Enums\TaggableSource;
use App\Models\Lead;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sync tags for a Lead. Accepts an array of existing tag IDs and/or new tag
 * names that are created on-the-fly via Tag::findOrCreateBySlug.
 */
class LeadTagController extends Controller
{
    /**
     * Handle POST /leads/{lead}/tags.
     *
     * The lead is resolved through Lead::production() so playground/sandbox
     * leads (is_sandbox = true) cannot be tagged via this endpoint — tags
     * are a production-only concept on the conversas inbox.
     */
    public function __invoke(Request $request, int $leadId): Response
    {
        $tenantId = (string) $request->user()->tenantId;

        // Resolve the lead through the production scope so sandbox leads 404.
        // The BelongsToTenant global scope on Lead handles cross-tenant isolation.
        $lead = Lead::production()->findOrFail($leadId);

        $data = $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                ),
            ],
            'tag_names' => ['nullable', 'array'],
            'tag_names.*' => ['string', 'min:1', 'max:50'],
            'source' => ['nullable', Rule::enum(TaggableSource::class)],
        ]);

        $userId = $request->user()->id;
        $source = isset($data['source'])
            ? TaggableSource::from($data['source'])
            : TaggableSource::default();

        $ids = collect($data['tag_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach (($data['tag_names'] ?? []) as $name) {
            $tag = Tag::findOrCreateBySlug(tenantId: $tenantId, name: (string) $name, userId: $userId);
            $ids[] = $tag->id;
        }

        $ids = array_values(array_unique($ids));

        $lead->syncTags($ids, source: $source, userId: $userId);

        // Inertia requests get a redirect; programmatic clients (tests, API) get JSON.
        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json([
            'lead_id' => $lead->id,
            'tags' => $lead->tags()->get(['tags.id', 'tags.name', 'tags.slug', 'tags.color', 'tags.is_hot']),
        ]);
    }
}
