<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates the standard local admin user with owner tenant access', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'admin@admin.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Admin')
        ->and(Hash::check('admin', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->tenants)->toHaveCount(1)
        ->and($user->tenants->first()->pivot->role)->toBe(TenantRole::Owner->value);
});

it('updates the standard local admin user without duplicating it', function () {
    $tenant = Tenant::create(['name' => 'Existing Tenant']);
    $user = User::create([
        'name' => 'Old Name',
        'email' => 'admin@admin.com',
        'password' => Hash::make('old-password'),
    ]);

    $user->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    $this->seed(DatabaseSeeder::class);

    $user->refresh();

    expect(User::query()->where('email', 'admin@admin.com')->count())->toBe(1)
        ->and($user->name)->toBe('Admin')
        ->and(Hash::check('admin', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->tenants()->whereKey($tenant->id)->first()->pivot->role)->toBe(TenantRole::Owner->value);
});
