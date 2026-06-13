<?php

use App\Exceptions\Tag\TagLimitReachedException;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag per-tenant hard cap (D5)', function () {
    test('tenant at the cap cannot create a 51st tag via API', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        Tag::factory()->forTenant($tenantId)->count(Tag::MAX_PER_TENANT)->create();

        $this->actingAs($user)
            ->postJson('/tags', [
                'name' => 'Excedente',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        expect(Tag::query()->forTenant($tenantId)->count())
            ->toBe(Tag::MAX_PER_TENANT);
    });

    test('tenant one below cap can still create a tag', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        Tag::factory()->forTenant($tenantId)->count(Tag::MAX_PER_TENANT - 1)->create();

        $this->actingAs($user)
            ->postJson('/tags', [
                'name' => 'Última vaga',
            ])
            ->assertCreated();

        expect(Tag::query()->forTenant($tenantId)->count())
            ->toBe(Tag::MAX_PER_TENANT);
    });

    test('findOrCreateBySlug throws TagLimitReachedException when capped', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        Tag::factory()->forTenant($tenantId)->count(Tag::MAX_PER_TENANT)->create();

        expect(fn () => Tag::findOrCreateBySlug($tenantId, 'Nova IA Tag'))
            ->toThrow(TagLimitReachedException::class);
    });

    test('findOrCreateBySlug returns existing tag without raising at the cap', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        $existing = Tag::createForTenant($tenantId, ['name' => 'Reutilizada']);
        Tag::factory()->forTenant($tenantId)->count(Tag::MAX_PER_TENANT - 1)->create();

        expect(Tag::query()->forTenant($tenantId)->count())->toBe(Tag::MAX_PER_TENANT);

        $resolved = Tag::findOrCreateBySlug($tenantId, 'Reutilizada');

        expect($resolved->id)->toBe($existing->id);
    });
});
