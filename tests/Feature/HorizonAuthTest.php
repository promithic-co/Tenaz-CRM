<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

describe('Horizon Auth Gate', function () {
    test('allows any authenticated user when no admin emails configured', function () {
        config(['credflow.admin_emails' => []]);

        $user = User::factory()->create(['email' => 'anyone@example.com']);

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    });

    test('allows user whose email is in admin list', function () {
        config(['credflow.admin_emails' => ['admin@tenazcrm.com.br']]);

        $user = User::factory()->create(['email' => 'admin@tenazcrm.com.br']);

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    });

    test('denies user whose email is not in admin list', function () {
        config(['credflow.admin_emails' => ['admin@tenazcrm.com.br']]);

        $user = User::factory()->create(['email' => 'other@example.com']);

        $this->assertFalse(Gate::forUser($user)->allows('viewHorizon'));
    });
});
