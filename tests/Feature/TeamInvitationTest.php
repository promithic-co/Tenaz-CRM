<?php

use App\Enums\TenantRole;
use App\Mail\TenantInvitationMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function makeTenantMember(string $role = 'owner'): array
{
    $tenant = Tenant::create(['name' => 'Acme']);
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['role' => $role]);

    return [$user, $tenant];
}

it('owner can send invitation and mail is queued', function () {
    Mail::fake();
    [$owner, $tenant] = makeTenantMember(TenantRole::Owner->value);

    $this->actingAs($owner)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post('/settings/team/invitations', [
            'email' => 'new@example.com',
            'role' => TenantRole::User->value,
        ])
        ->assertRedirect();

    expect(TenantInvitation::where('email', 'new@example.com')->exists())->toBeTrue();
    Mail::assertQueued(TenantInvitationMail::class);
});

it('administrator can see team index and list pending invitations', function () {
    [$admin, $tenant] = makeTenantMember(TenantRole::Administrator->value);
    TenantInvitation::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'pending@example.com',
    ]);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/settings/team')
        ->assertOk();
});

it('restricted user cannot access team settings', function () {
    [$user, $tenant] = makeTenantMember(TenantRole::User->value);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/settings/team')
        ->assertForbidden();
});

it('owner can cancel a pending invitation', function () {
    [$owner, $tenant] = makeTenantMember(TenantRole::Owner->value);
    $invitation = TenantInvitation::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($owner)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->delete("/settings/team/invitations/{$invitation->id}")
        ->assertRedirect();

    expect(TenantInvitation::find($invitation->id))->toBeNull();
});

it('rejects duplicate pending invitation for same email in tenant', function () {
    Mail::fake();
    [$owner, $tenant] = makeTenantMember(TenantRole::Owner->value);
    TenantInvitation::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'dup@example.com',
    ]);

    $this->actingAs($owner)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post('/settings/team/invitations', [
            'email' => 'dup@example.com',
            'role' => TenantRole::User->value,
        ])
        ->assertSessionHasErrors('email');
});
