<?php

use App\Enums\TenantRole;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Lead tag sync endpoints', function () {
    test('sync attaches existing tag ids to lead', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->create();
        $t1 = Tag::createForTenant((string) $user->tenantId, ['name' => 'A']);
        $t2 = Tag::createForTenant((string) $user->tenantId, ['name' => 'B']);

        $this->actingAs($user)
            ->postJson('/leads/'.$lead->id.'/tags', [
                'tag_ids' => [$t1->id, $t2->id],
            ])
            ->assertOk()
            ->assertJsonPath('lead_id', $lead->id)
            ->assertJsonCount(2, 'tags');

        expect($lead->tags()->count())->toBe(2);
    });

    test('sync creates new tags from tag_names on the fly', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->create();

        $this->actingAs($user)
            ->postJson('/leads/'.$lead->id.'/tags', [
                'tag_names' => ['Indicação Carol', 'VIP'],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'tags');

        expect($lead->hasTag('indicacao-carol'))->toBeTrue()
            ->and($lead->hasTag('vip'))->toBeTrue();
    });

    test('sync replaces existing tags', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->create();
        $t1 = Tag::createForTenant((string) $user->tenantId, ['name' => 'Old']);
        $t2 = Tag::createForTenant((string) $user->tenantId, ['name' => 'New']);

        $lead->attachTag($t1);

        $this->actingAs($user)
            ->postJson('/leads/'.$lead->id.'/tags', [
                'tag_ids' => [$t2->id],
            ])
            ->assertOk();

        expect($lead->tags()->pluck('tags.id')->all())->toBe([$t2->id]);
        expect($t1->fresh()->usage_count)->toBe(0);
        expect($t2->fresh()->usage_count)->toBe(1);
    });

    test('cross-tenant lead sync returns 404', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $userA->tenantId)->create();

        $this->actingAs($userB)
            ->postJson('/leads/'.$lead->id.'/tags', [
                'tag_names' => ['Roubada'],
            ])
            ->assertNotFound();
    });

    test('sync rejects tag_ids belonging to another tenant', function () {
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $attacker->tenantId)->create();
        $foreignTag = Tag::createForTenant((string) $victim->tenantId, ['name' => 'segredoVitima']);

        $this->actingAs($attacker)
            ->postJson('/leads/'.$lead->id.'/tags', [
                'tag_ids' => [$foreignTag->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tag_ids.0');

        expect($lead->tags()->count())->toBe(0);
    });

    test('sandbox lead returns 404 because tag sync is production-only', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->sandbox()->create();

        $this->actingAs($user)
            ->postJson('/leads/'.$lead->id.'/tags', [
                'tag_names' => ['Roubada'],
            ])
            ->assertNotFound();
    });

    test('invalid source value returns 422', function () {
        $user = User::factory()->create();
        $lead = Lead::factory()->forTenant((string) $user->tenantId)->create();

        $this->actingAs($user)
            ->postJson('/leads/'.$lead->id.'/tags', [
                'tag_names' => ['vip'],
                'source' => 'rogue',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('source');

        expect($lead->tags()->count())->toBe(0);
    });

    test('restricted user is forbidden from syncing lead tags', function () {
        $tenant = Tenant::create(['name' => 'Acme RBAC']);
        $member = User::factory()->create();
        $member->tenants()->detach();
        $member->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

        $lead = Lead::factory()->forTenant((string) $tenant->id)->create();

        $this->actingAs($member)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->postJson('/leads/'.$lead->id.'/tags', [
                'tag_names' => ['Manual'],
            ])
            ->assertForbidden();
    });
});
