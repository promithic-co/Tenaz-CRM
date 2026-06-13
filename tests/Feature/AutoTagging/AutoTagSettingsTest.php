<?php

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('AutoTag Settings', function () {
    test('auto_tagging_enabled defaults to false', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        $value = AppSetting::getForTenant($tenantId, 'auto_tagging_enabled', false);

        expect($value)->toBeFalsy();
    });

    test('PATCH settings/auto-tag persists value', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('auto-tag.update'), ['auto_tagging_enabled' => true])
            ->assertRedirect();

        $tenantId = (string) $user->tenantId;
        Cache::flush();
        $value = AppSetting::getForTenant($tenantId, 'auto_tagging_enabled', false);

        expect((bool) $value)->toBeTrue();
    });

    test('non-owner/admin cannot change auto_tagging_enabled', function () {
        // Create a user (factory gives them owner role in their own tenant)
        $member = User::factory()->create();
        $tenantId = (string) $member->tenantId;

        // Downgrade to 'user' role (not owner/administrator)
        $member->tenants()->updateExistingPivot($tenantId, ['role' => \App\Enums\TenantRole::User->value]);

        $this->actingAs($member)
            ->patch(route('auto-tag.update'), ['auto_tagging_enabled' => true])
            ->assertForbidden();
    });
});
