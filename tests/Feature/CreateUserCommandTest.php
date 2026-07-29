<?php

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user via the command', function () {
    $this->artisan('credflow:create-user')
        ->expectsQuestion('Full name', 'João Silva')
        ->expectsQuestion('Company name', 'Acme')
        ->expectsQuestion('E-mail', 'joao@example.com')
        ->expectsQuestion('Password', 'a-secure-password')
        ->assertSuccessful();

    $user = User::where('email', 'joao@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('João Silva')
        ->and($user->email)->toBe('joao@example.com')
        ->and($user->tenants)->toHaveCount(1)
        ->and($user->tenants->first()->name)->toBe('Acme')
        ->and($user->tenants->first()->pivot->role)->toBe(TenantRole::Owner->value);
});

it('fails when email is already taken', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('credflow:create-user')
        ->expectsQuestion('Full name', 'Outro')
        ->expectsQuestion('Company name', 'Outra Empresa')
        ->expectsQuestion('E-mail', 'existing@example.com')
        ->expectsQuestion('Password', 'a-secure-password')
        ->assertFailed();
});

it('rejects passwords shorter than fifteen characters', function () {
    $this->artisan('credflow:create-user')
        ->expectsQuestion('Full name', 'João Silva')
        ->expectsQuestion('Company name', 'Acme')
        ->expectsQuestion('E-mail', 'joao@example.com')
        ->expectsQuestion('Password', 'short-password')
        ->assertFailed();

    expect(User::where('email', 'joao@example.com')->exists())->toBeFalse();
});

it('rejects registration via the web', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertNotFound();
});
