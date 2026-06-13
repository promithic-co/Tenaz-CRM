<?php

use App\Enums\TaggableSource;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('HasTags source-aware semantics (D2)', function () {
    test('attachTag with TaggableSource::Ai stores ai pivot source', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        $lead = Lead::factory()->forTenant($tenantId)->create();
        $tag = Tag::createForTenant($tenantId, ['name' => 'Hot']);

        $lead->attachTag($tag, TaggableSource::Ai);

        $pivot = $lead->tags()->wherePivot('source', 'ai')->first();
        expect($pivot)->not->toBeNull()
            ->and($pivot->id)->toBe($tag->id);
    });

    test('detachTag callerSource Ai cannot remove manual pivot', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        $lead = Lead::factory()->forTenant($tenantId)->create();
        $manualTag = Tag::createForTenant($tenantId, ['name' => 'VIP']);

        $lead->attachTag($manualTag, TaggableSource::Manual, $user->id);

        $lead->detachTag($manualTag, TaggableSource::Ai);

        expect($lead->tags()->count())->toBe(1)
            ->and($lead->hasTag('vip'))->toBeTrue();
    });

    test('detachTag callerSource Manual removes ai-created pivot (human overrides)', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        $lead = Lead::factory()->forTenant($tenantId)->create();
        $aiTag = Tag::createForTenant($tenantId, ['name' => 'Maybe']);

        $lead->attachTag($aiTag, TaggableSource::Ai);

        $lead->detachTag($aiTag, TaggableSource::Manual);

        expect($lead->tags()->count())->toBe(0);
    });

    test('syncTags with Ai source only reconciles ai pivots, leaves manual intact', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        $lead = Lead::factory()->forTenant($tenantId)->create();

        $manualA = Tag::createForTenant($tenantId, ['name' => 'M1']);
        $manualB = Tag::createForTenant($tenantId, ['name' => 'M2']);
        $aiOld = Tag::createForTenant($tenantId, ['name' => 'A1']);
        $aiNew = Tag::createForTenant($tenantId, ['name' => 'A2']);

        $lead->attachTag($manualA, TaggableSource::Manual, $user->id);
        $lead->attachTag($manualB, TaggableSource::Manual, $user->id);
        $lead->attachTag($aiOld, TaggableSource::Ai);

        // AI now syncs to a fresh set: drop aiOld, keep aiNew.
        $lead->syncTags([$aiNew->id], TaggableSource::Ai);

        $slugs = $lead->tags()->pluck('tags.slug')->sort()->values()->all();

        expect($slugs)->toBe(['a2', 'm1', 'm2'])
            ->and($aiOld->fresh()->usage_count)->toBe(0)
            ->and($aiNew->fresh()->usage_count)->toBe(1)
            ->and($manualA->fresh()->usage_count)->toBe(1)
            ->and($manualB->fresh()->usage_count)->toBe(1);
    });

    test('syncTags with empty array and Ai source removes only ai pivots', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        $lead = Lead::factory()->forTenant($tenantId)->create();

        $manualTag = Tag::createForTenant($tenantId, ['name' => 'Manual']);
        $aiTag = Tag::createForTenant($tenantId, ['name' => 'AiOnly']);

        $lead->attachTag($manualTag, TaggableSource::Manual, $user->id);
        $lead->attachTag($aiTag, TaggableSource::Ai);

        $lead->syncTags([], TaggableSource::Ai);

        expect($lead->tags()->count())->toBe(1)
            ->and($lead->hasTag('manual'))->toBeTrue()
            ->and($lead->hasTag('aionly'))->toBeFalse();
    });

    test('detachTag without callerSource removes any pivot (legacy unscoped path)', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        $lead = Lead::factory()->forTenant($tenantId)->create();
        $tag = Tag::createForTenant($tenantId, ['name' => 'Any']);

        $lead->attachTag($tag, TaggableSource::Ai);

        $lead->detachTag($tag);

        expect($lead->tags()->count())->toBe(0);
    });
});
