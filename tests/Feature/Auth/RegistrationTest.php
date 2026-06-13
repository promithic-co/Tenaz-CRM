<?php

// Registration is closed — users are created via: php artisan credflow:create-user

test('registration screen is not accessible', function () {
    $this->get('/register')->assertNotFound();
});

test('new users cannot register via the web', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});
