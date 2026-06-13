<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag AI-detectable validation', function () {
    test('ai_detectable=true without ai_description fails validation', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('tags.store'), [
                'name' => 'Test Tag',
                'color' => 'blue',
                'ai_detectable' => true,
                // missing ai_description
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_description');
    });

    test('ai_min_confidence must be numeric between 0.50 and 0.99', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('tags.store'), [
                'name' => 'Test Tag',
                'color' => 'blue',
                'ai_detectable' => true,
                'ai_description' => 'Valid description',
                'ai_min_confidence' => 1.5, // out of range
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_min_confidence');
    });

    test('valid ai_detectable tag stores correctly', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        $this->actingAs($user)
            ->postJson(route('tags.store'), [
                'name' => 'AI Tag',
                'color' => 'green',
                'ai_detectable' => true,
                'ai_description' => 'Detects leads with strong buying signals',
                'ai_min_confidence' => 0.75,
            ])
            ->assertStatus(201);

        $tag = Tag::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('slug', 'ai-tag')
            ->first();

        expect($tag)->not->toBeNull();
        expect($tag->ai_detectable)->toBeTrue();
        expect($tag->ai_description)->toBe('Detects leads with strong buying signals');
        expect((float) $tag->ai_min_confidence)->toBe(0.75);
    });
});
