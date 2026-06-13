<?php

use App\Ai\Agents\LeadSignalExtractorAgent;
use App\Enums\TaggableSource;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use App\Services\LeadAutoTaggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function makeAutoTagTenant(): array
{
    $user = User::factory()->create();
    $tenantId = (string) $user->tenantId;
    \App\Models\AppSetting::setForTenant($tenantId, 'auto_tagging_enabled', true);

    return [$user, $tenantId];
}

describe('LeadAutoTaggingService', function () {
    test('no detectable tags returns skipped result without calling LLM', function () {
        [$user, $tenantId] = makeAutoTagTenant();
        $lead = Lead::factory()->forTenant($tenantId)->create();

        // Create tags but none are ai_detectable
        Tag::factory()->forTenant($tenantId)->create(['ai_detectable' => false]);

        LeadSignalExtractorAgent::fake([]);

        $service = app(LeadAutoTaggingService::class);
        $result = $service->evaluate($lead, 'test');

        expect($result['skipped'])->toBeTrue();
        LeadSignalExtractorAgent::assertNeverPrompted();
    });

    test('unknown LLM slug is ignored and not attached', function () {
        [$user, $tenantId] = makeAutoTagTenant();
        $lead = Lead::factory()->forTenant($tenantId)->create();

        $tag = Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'Test tag',
            'ai_min_confidence' => 0.70,
        ]);

        LeadSignalExtractorAgent::fake([[
            'detected' => [
                ['slug' => 'unknown-slug-xyz', 'confidence' => 0.95, 'evidence' => 'test evidence'],
            ],
        ]]);

        $service = app(LeadAutoTaggingService::class);
        $result = $service->evaluate($lead, 'test');

        expect($lead->tags()->count())->toBe(0);
        expect($result['applied'] ?? [])->toBeEmpty();
    });

    test('confidence below ai_min_confidence is ignored', function () {
        [$user, $tenantId] = makeAutoTagTenant();
        $lead = Lead::factory()->forTenant($tenantId)->create();

        $tag = Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'High confidence required',
            'ai_min_confidence' => 0.85,
        ]);

        LeadSignalExtractorAgent::fake([[
            'detected' => [
                ['slug' => $tag->slug, 'confidence' => 0.70, 'evidence' => 'low confidence evidence'],
            ],
        ]]);

        $service = app(LeadAutoTaggingService::class);
        $service->evaluate($lead, 'test');

        expect($lead->tags()->count())->toBe(0);
    });

    test('valid tag is attached with TaggableSource::Ai', function () {
        [$user, $tenantId] = makeAutoTagTenant();
        $lead = Lead::factory()->forTenant($tenantId)->create();

        $tag = Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'Qualified lead',
            'ai_min_confidence' => 0.70,
        ]);

        LeadSignalExtractorAgent::fake([[
            'detected' => [
                ['slug' => $tag->slug, 'confidence' => 0.90, 'evidence' => 'client showed strong interest'],
            ],
        ]]);

        $service = app(LeadAutoTaggingService::class);
        $service->evaluate($lead, 'status_change');

        $pivot = $lead->fresh()->tags()->where('tags.id', $tag->id)->first();
        expect($pivot)->not->toBeNull();
        expect($pivot->pivot->source)->toBe(TaggableSource::Ai->value);
    });

    test('tag from different tenant is ignored', function () {
        [$user1, $tenantId1] = makeAutoTagTenant();
        [$user2, $tenantId2] = makeAutoTagTenant();

        $lead = Lead::factory()->forTenant($tenantId1)->create();

        // Tag belongs to tenant2, not tenant1
        $otherTenantTag = Tag::factory()->forTenant($tenantId2)->create([
            'ai_detectable' => true,
            'ai_description' => 'Other tenant tag',
        ]);

        // Also create a tenant1 tag so LLM gets called
        $tenant1Tag = Tag::factory()->forTenant($tenantId1)->create([
            'ai_detectable' => true,
            'ai_description' => 'Tenant 1 tag',
            'ai_min_confidence' => 0.70,
        ]);

        LeadSignalExtractorAgent::fake([[
            'detected' => [
                ['slug' => $otherTenantTag->slug, 'confidence' => 0.95, 'evidence' => 'from other tenant'],
            ],
        ]]);

        $service = app(LeadAutoTaggingService::class);
        $service->evaluate($lead, 'test');

        // The slug from tenant2 is not in tenant1's whitelist, so it's ignored
        expect($lead->tags()->count())->toBe(0);
    });

    test('manual tag is never removed or overwritten by AI', function () {
        [$user, $tenantId] = makeAutoTagTenant();
        $lead = Lead::factory()->forTenant($tenantId)->create();

        $tag = Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'Interest signal',
            'ai_min_confidence' => 0.70,
        ]);

        // Manually attach the tag first
        $lead->attachTag($tag, TaggableSource::Manual, $user->id);

        LeadSignalExtractorAgent::fake([[
            'detected' => [
                ['slug' => $tag->slug, 'confidence' => 0.95, 'evidence' => 'AI also detected this'],
            ],
        ]]);

        $service = app(LeadAutoTaggingService::class);
        $service->evaluate($lead, 'test');

        // Tag still exists and source is still Manual
        $pivot = $lead->fresh()->tags()->where('tags.id', $tag->id)->first();
        expect($pivot)->not->toBeNull();
        expect($pivot->pivot->source)->toBe(TaggableSource::Manual->value);
        expect($lead->fresh()->tags()->count())->toBe(1);
    });

    test('manually attaching an AI-owned tag promotes source to Manual', function () {
        [$user, $tenantId] = makeAutoTagTenant();
        $lead = Lead::factory()->forTenant($tenantId)->create();

        $tag = Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'AI tag',
        ]);

        // AI attaches the tag first
        $lead->attachTag($tag, TaggableSource::Ai);
        expect($lead->tags()->where('tags.id', $tag->id)->first()->pivot->source)->toBe(TaggableSource::Ai->value);

        // Human manually re-attaches
        $lead->detachTag($tag, TaggableSource::Manual);
        $lead->attachTag($tag, TaggableSource::Manual, $user->id);

        $pivot = $lead->fresh()->tags()->where('tags.id', $tag->id)->first();
        expect($pivot->pivot->source)->toBe(TaggableSource::Manual->value);
    });

    test('auto_tagging_enabled=false skips evaluation without LLM call', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        // NOT enabling auto_tagging_enabled
        $lead = Lead::factory()->forTenant($tenantId)->create();

        Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'Test',
        ]);

        LeadSignalExtractorAgent::fake([]);

        $service = app(LeadAutoTaggingService::class);
        $result = $service->evaluate($lead, 'test');

        expect($result['skipped'])->toBeTrue();
        LeadSignalExtractorAgent::assertNeverPrompted();
    });
});
