<?php

use App\Enums\TenantRole;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seatMemberForTags(Tenant $tenant, string $role): User
{
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => $role]);

    return $user;
}

describe('TagController', function () {
    test('store creates a tag with derived slug', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/tags', ['name' => 'VIP Cliente', 'color' => 'red'])
            ->assertCreated()
            ->assertJsonPath('slug', 'vip-cliente')
            ->assertJsonPath('color', 'red');

        expect(Tag::query()->where('slug', 'vip-cliente')->exists())->toBeTrue();
    });

    test('store rejects duplicate name in the same tenant', function () {
        $user = User::factory()->create();
        Tag::createForTenant((string) $user->tenantId, ['name' => 'Idoso']);

        $this->actingAs($user)
            ->postJson('/tags', ['name' => 'Idoso'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    });

    test('update renames tag and re-derives slug', function () {
        $user = User::factory()->create();
        $tag = Tag::createForTenant((string) $user->tenantId, ['name' => 'Antigo']);

        $this->actingAs($user)
            ->patchJson('/tags/'.$tag->id, ['name' => 'Novo Nome'])
            ->assertOk()
            ->assertJsonPath('slug', 'novo-nome');
    });

    test('destroy soft-deletes the tag', function () {
        $user = User::factory()->create();
        $tag = Tag::createForTenant((string) $user->tenantId, ['name' => 'Apagar']);

        $this->actingAs($user)
            ->deleteJson('/tags/'.$tag->id)
            ->assertOk();

        expect(Tag::query()->find($tag->id))->toBeNull();
        expect(Tag::withTrashed()->find($tag->id))->not->toBeNull();
    });

    test('cross-tenant update returns 404 via global scope', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $tag = Tag::createForTenant((string) $userA->tenantId, ['name' => 'SegredoA']);

        $this->actingAs($userB)
            ->patchJson('/tags/'.$tag->id, ['name' => 'Roubada'])
            ->assertNotFound();
    });

    test('index returns only the current tenants tags', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Tag::createForTenant((string) $userA->tenantId, ['name' => 'Alfa']);
        Tag::createForTenant((string) $userB->tenantId, ['name' => 'Beta']);

        $payload = $this->actingAs($userA)
            ->getJson('/tags')
            ->assertOk()
            ->json('data');

        expect(collect($payload)->pluck('slug')->all())->toBe(['alfa']);
    });

    test('restricted user is forbidden from creating tags', function () {
        $tenant = Tenant::create(['name' => 'Acme RBAC']);
        $member = seatMemberForTags($tenant, TenantRole::User->value);

        $this->actingAs($member)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->postJson('/tags', ['name' => 'Proibida'])
            ->assertForbidden();
    });

    test('restricted user is forbidden from updating tags', function () {
        $tenant = Tenant::create(['name' => 'Acme RBAC']);
        $owner = seatMemberForTags($tenant, TenantRole::Owner->value);
        $member = seatMemberForTags($tenant, TenantRole::User->value);

        $tag = Tag::createForTenant((string) $owner->tenantId, ['name' => 'Importante']);

        $this->actingAs($member)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->patchJson('/tags/'.$tag->id, ['name' => 'Modificada'])
            ->assertForbidden();
    });

    test('restricted user is forbidden from deleting tags', function () {
        $tenant = Tenant::create(['name' => 'Acme RBAC']);
        $owner = seatMemberForTags($tenant, TenantRole::Owner->value);
        $member = seatMemberForTags($tenant, TenantRole::User->value);

        $tag = Tag::createForTenant((string) $owner->tenantId, ['name' => 'NaoPodeApagar']);

        $this->actingAs($member)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->deleteJson('/tags/'.$tag->id)
            ->assertForbidden();
    });

    test('administrator can create tags', function () {
        $tenant = Tenant::create(['name' => 'Acme RBAC']);
        $admin = seatMemberForTags($tenant, TenantRole::Administrator->value);

        $this->actingAs($admin)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->postJson('/tags', ['name' => 'PermitidaAdmin'])
            ->assertCreated();
    });

    test('restricted user can still list tags (read-only access)', function () {
        $tenant = Tenant::create(['name' => 'Acme RBAC']);
        $member = seatMemberForTags($tenant, TenantRole::User->value);

        $this->actingAs($member)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->getJson('/tags')
            ->assertOk();
    });
});
