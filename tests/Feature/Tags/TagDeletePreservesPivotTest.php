<?php

use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Tag soft-delete preserves pivots (D7)', function () {
    test('soft-deleting a tag leaves taggables rows in place', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        $lead = Lead::factory()->forTenant($tenantId)->create();
        $tag = Tag::factory()->forTenant($tenantId)->create();

        $lead->attachTag($tag);

        $pivotCount = DB::table('taggables')->where('tag_id', $tag->id)->count();
        expect($pivotCount)->toBe(1);

        $tag->delete();

        $pivotCountAfter = DB::table('taggables')->where('tag_id', $tag->id)->count();
        expect($pivotCountAfter)->toBe(1);
    });

    test('soft-deleted tag is hidden from the morph relation', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        $lead = Lead::factory()->forTenant($tenantId)->create();
        $tag = Tag::factory()->forTenant($tenantId)->create();

        $lead->attachTag($tag);
        $tag->delete();

        expect($lead->tags()->count())->toBe(0);
        expect($lead->tags()->withTrashed()->count())->toBe(1);
    });

    test('restoring a soft-deleted tag re-exposes the pivot', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        $lead = Lead::factory()->forTenant($tenantId)->create();
        $tag = Tag::factory()->forTenant($tenantId)->create();

        $lead->attachTag($tag);
        $tag->delete();
        $tag->restore();

        expect($lead->tags()->count())->toBe(1);
        expect($lead->tags()->first()->id)->toBe($tag->id);
    });
});
