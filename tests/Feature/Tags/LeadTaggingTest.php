<?php

use App\Enums\TaggableSource;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('HasTags trait on Lead', function () {
    test('attachTag creates pivot and increments usage_count', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->create();
        $tag = Tag::createForTenant((string) $user->tenantId, [
            'name' => 'VIP',
            'color' => 'red',
        ]);

        $lead->attachTag($tag, source: TaggableSource::Manual, userId: $user->id);

        expect($lead->tags()->count())->toBe(1)
            ->and($lead->hasTag('vip'))->toBeTrue();
        expect($tag->fresh()->usage_count)->toBe(1);
    });

    test('attaching the same tag twice does not duplicate or double-count', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->create();
        $tag = Tag::createForTenant((string) $user->tenantId, ['name' => 'Idoso']);

        $lead->attachTag($tag);
        $lead->attachTag($tag);

        expect($lead->tags()->count())->toBe(1);
        expect($tag->fresh()->usage_count)->toBe(1);
    });

    test('detachTag removes pivot and decrements usage_count', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->create();
        $tag = Tag::createForTenant((string) $user->tenantId, ['name' => 'Quente']);

        $lead->attachTag($tag);
        $lead->detachTag($tag);

        expect($lead->tags()->count())->toBe(0);
        expect($tag->fresh()->usage_count)->toBe(0);
    });

    test('attachTag by slug creates tag on the fly', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->create();

        $lead->attachTag('Indicação Carol');

        expect($lead->hasTag('indicacao-carol'))->toBeTrue();
        $tag = Tag::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $user->tenantId)
            ->where('slug', 'indicacao-carol')
            ->first();
        expect($tag)->not->toBeNull();
        expect($tag->usage_count)->toBe(1);
    });

    test('syncTags adds and removes tags correctly with usage counts', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->create();
        $t1 = Tag::createForTenant((string) $user->tenantId, ['name' => 'A']);
        $t2 = Tag::createForTenant((string) $user->tenantId, ['name' => 'B']);
        $t3 = Tag::createForTenant((string) $user->tenantId, ['name' => 'C']);

        $lead->syncTags([$t1->id, $t2->id]);
        expect($lead->tags()->pluck('tags.id')->sort()->values()->all())->toBe([$t1->id, $t2->id]);
        expect($t1->fresh()->usage_count)->toBe(1);
        expect($t2->fresh()->usage_count)->toBe(1);

        $lead->syncTags([$t2->id, $t3->id]);
        expect($lead->tags()->pluck('tags.id')->sort()->values()->all())->toBe([$t2->id, $t3->id]);
        expect($t1->fresh()->usage_count)->toBe(0);
        expect($t2->fresh()->usage_count)->toBe(1);
        expect($t3->fresh()->usage_count)->toBe(1);
    });
});

describe('Tag::findOrCreateBySlug soft-delete behavior', function () {
    test('restores a soft-deleted tag when slug collides', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        $tag = Tag::createForTenant($tenantId, ['name' => 'Restaurar']);
        $tagId = $tag->id;
        $tag->delete();

        expect(Tag::query()->find($tagId))->toBeNull();
        expect(Tag::withTrashed()->find($tagId))->not->toBeNull();

        $resolved = Tag::findOrCreateBySlug(tenantId: $tenantId, name: 'Restaurar');

        expect($resolved->id)->toBe($tagId)
            ->and($resolved->trashed())->toBeFalse()
            ->and(Tag::query()->find($tagId))->not->toBeNull();
    });
});

describe('Tag tenant isolation', function () {
    test('tags from tenant A are not visible from tenant B context', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Tag::createForTenant((string) $userA->tenantId, ['name' => 'SegredoA']);

        $this->actingAs($userB);

        expect(Tag::query()->where('slug', 'segredoa')->exists())->toBeFalse();
    });
});
