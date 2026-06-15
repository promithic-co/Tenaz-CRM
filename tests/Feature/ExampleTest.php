<?php

use App\Models\User;

test('guests are redirected from the root to login', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('authenticated users are redirected from the root to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
