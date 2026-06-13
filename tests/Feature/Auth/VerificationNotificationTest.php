<?php

// Email verification notification routes are disabled.
// Users are created pre-verified via: php artisan credflow:create-user

test('verification notification routes are not accessible', function () {
    $this->post('/email/verification-notification')->assertNotFound();
});
