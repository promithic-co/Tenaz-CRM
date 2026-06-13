<?php

use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag is_hot flag (D3)', function () {
    test('is_hot column persists when creating a tag via API', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/tags', [
                'name' => 'Lead Quente',
                'color' => 'red',
                'is_hot' => true,
            ])
            ->assertCreated();

        $tag = Tag::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $user->tenantId)
            ->where('slug', 'lead-quente')
            ->firstOrFail();

        expect($tag->is_hot)->toBeTrue();
    });

    test('is_hot defaults to false when omitted on create', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/tags', [
                'name' => 'Descritiva',
            ])
            ->assertCreated();

        $tag = Tag::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $user->tenantId)
            ->where('slug', 'descritiva')
            ->firstOrFail();

        expect($tag->is_hot)->toBeFalse();
    });

    test('is_hot can be toggled via update endpoint', function () {
        $user = User::factory()->create();
        $tag = Tag::createForTenant((string) $user->tenantId, ['name' => 'Toggle']);

        expect($tag->is_hot)->toBeFalse();

        $this->actingAs($user)
            ->patchJson('/tags/'.$tag->id, [
                'name' => 'Toggle',
                'is_hot' => true,
            ])
            ->assertOk();

        expect($tag->fresh()->is_hot)->toBeTrue();
    });

    test('Lead::hasHotTag returns true when any attached tag is hot', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        $lead = Lead::factory()->forTenant($tenantId)->create();
        $hot = Tag::factory()->forTenant($tenantId)->hot()->create();
        $cold = Tag::factory()->forTenant($tenantId)->create();

        $lead->attachTag($cold);
        expect($lead->hasHotTag())->toBeFalse();

        $lead->attachTag($hot);
        expect($lead->hasHotTag())->toBeTrue();
    });

    test('TagFactory hot() state sets is_hot=true', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->forTenant((string) $user->tenantId)->hot()->create();

        expect($tag->is_hot)->toBeTrue();
    });
});
