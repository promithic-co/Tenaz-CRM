<?php

use App\Models\User;

// Email verification routes are disabled — all users created via credflow:create-user
// are auto-verified (email_verified_at is set on creation).

test('users created by command have verified email', function () {
    $user = User::factory()->create();

    expect($user->hasVerifiedEmail())->toBeTrue();
});

test('unverified users are treated as having verified email since verification is disabled', function () {
    $user = User::factory()->unverified()->create();

    // The verified middleware passes because User does not implement MustVerifyEmail,
    // so protected routes are accessible regardless of email_verified_at.
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});
