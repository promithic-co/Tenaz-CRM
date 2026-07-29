<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createPendingInvitation(string $email, string $role = 'user'): array
{
    $tenant = Tenant::create(['name' => 'Acme']);
    $plain = Str::random(48);
    $invitation = TenantInvitation::create([
        'tenant_id' => $tenant->id,
        'invited_by_user_id' => null,
        'email' => $email,
        'role' => $role,
        'token' => TenantInvitation::hashToken($plain),
        'expires_at' => now()->addDays(7),
    ]);

    return [$invitation, $tenant, $plain];
}

it('accepting invitation creates user and attaches to tenant', function () {
    [$invitation, $tenant, $token] = createPendingInvitation('new@example.com');

    $this->post("/invite/{$token}", [
        'name' => 'New Member',
        'password' => 'password-strong',
        'password_confirmation' => 'password-strong',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->tenants()->first()->id)->toBe($tenant->id);
    expect($user->tenants()->first()->pivot->role)->toBe(TenantRole::User->value);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('rejects expired invitation', function () {
    $tenant = Tenant::create(['name' => 'Acme']);
    $token = Str::random(48);
    TenantInvitation::create([
        'tenant_id' => $tenant->id,
        'email' => 'late@example.com',
        'role' => TenantRole::User->value,
        'token' => TenantInvitation::hashToken($token),
        'expires_at' => now()->subDay(),
    ]);

    $this->get("/invite/{$token}")
        ->assertRedirect(route('login'));
});

it('rejects already accepted invitation', function () {
    [$invitation, , $token] = createPendingInvitation('used@example.com');
    $invitation->forceFill(['accepted_at' => now()])->save();

    $this->get("/invite/{$token}")
        ->assertRedirect(route('login'));
});

it('attaches existing user to tenant on acceptance', function () {
    $existing = User::factory()->create(['email' => 'exists@example.com', 'password' => Hash::make('secret-pass')]);
    [$invitation, $tenant, $token] = createPendingInvitation('exists@example.com', TenantRole::Administrator->value);

    $this->actingAs($existing)
        ->post("/invite/{$token}", [
            'password' => 'secret-pass',
        ])->assertRedirect();

    $this->assertAuthenticatedAs($existing);
    expect(User::where('email', 'exists@example.com')->count())->toBe(1);
    expect($existing->fresh()->tenants()->where('tenants.id', $tenant->id)->first()->pivot->role)
        ->toBe(TenantRole::Administrator->value);
});

it('rejects an incorrect current password for an existing user', function () {
    $existing = User::factory()->create([
        'email' => 'exists@example.com',
        'password' => Hash::make('secret-pass'),
    ]);
    [$invitation, $tenant, $token] = createPendingInvitation(
        'exists@example.com',
        TenantRole::Administrator->value,
    );

    $this->actingAs($existing)
        ->post("/invite/{$token}", [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

    $this->assertAuthenticatedAs($existing);
    expect($invitation->fresh()->accepted_at)->toBeNull()
        ->and($existing->fresh()->tenants()->where('tenants.id', $tenant->id)->exists())->toBeFalse();
});

it('requires normal authentication before an existing user can accept an invitation', function () {
    $existing = User::factory()->create([
        'email' => 'exists@example.com',
        'password' => Hash::make('secret-pass'),
    ]);
    [$invitation, $tenant, $token] = createPendingInvitation(
        'exists@example.com',
        TenantRole::Administrator->value,
    );

    $this->get("/invite/{$token}")
        ->assertRedirect(route('login'));

    $this->post("/invite/{$token}", [
        'password' => 'secret-pass',
    ])->assertForbidden();

    $this->assertGuest();
    expect($invitation->fresh()->accepted_at)->toBeNull()
        ->and($existing->fresh()->tenants()->where('tenants.id', $tenant->id)->exists())->toBeFalse();
});

it('rejects a short password when an invitation creates a user', function () {
    [$invitation, , $token] = createPendingInvitation('new@example.com');

    $this->post("/invite/{$token}", [
        'name' => 'New Member',
        'password' => 'short-password',
        'password_confirmation' => 'short-password',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
    expect(User::where('email', 'new@example.com')->exists())->toBeFalse()
        ->and($invitation->fresh()->accepted_at)->toBeNull();
});
