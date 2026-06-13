<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a user via the command', function () {
    $this->artisan('credflow:create-user')
        ->expectsQuestion('Full name', 'João Silva')
        ->expectsQuestion('E-mail', 'joao@example.com')
        ->expectsQuestion('Password', 'secret123')
        ->assertSuccessful();

    $user = User::where('email', 'joao@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('João Silva')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('fails when email is already taken', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('credflow:create-user')
        ->expectsQuestion('Full name', 'Outro')
        ->expectsQuestion('E-mail', 'existing@example.com')
        ->expectsQuestion('Password', 'secret123')
        ->assertFailed();
});

it('rejects registration via the web', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertNotFound();
});
