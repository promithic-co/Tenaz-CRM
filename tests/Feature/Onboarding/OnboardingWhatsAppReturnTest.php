<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Authenticate as an onboarded owner so the gate doesn't interfere.
function makeOnboardedOwner(): User
{
    return User::factory()->create(); // onboarded_at = now(), factory creates an owner tenant
}

test('whatsapp index with allowlisted return=/onboarding emits return_to = /onboarding', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
        '*' => Http::response([], 200),
    ]);

    $user = makeOnboardedOwner();

    $this->actingAs($user)
        ->get('/whatsapp?return=/onboarding')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('whatsapp/Index')
            ->where('return_to', '/onboarding')
        );
});

test('whatsapp index with arbitrary external return URL discards it (return_to = null)', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
        '*' => Http::response([], 200),
    ]);

    $user = makeOnboardedOwner();

    $this->actingAs($user)
        ->get('/whatsapp?return=https://evil.test/steal')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('whatsapp/Index')
            ->where('return_to', null)
        );
});

test('whatsapp index with no return param has return_to = null', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
        '*' => Http::response([], 200),
    ]);

    $user = makeOnboardedOwner();

    $this->actingAs($user)
        ->get('/whatsapp')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('whatsapp/Index')
            ->where('return_to', null)
        );
});

test('whatsapp index with non-allowlisted internal path discards it (return_to = null)', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
        '*' => Http::response([], 200),
    ]);

    $user = makeOnboardedOwner();

    $this->actingAs($user)
        ->get('/whatsapp?return=/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('whatsapp/Index')
            ->where('return_to', null)
        );
});

test('gated incomplete owner can GET /whatsapp and sees return_to prop', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response(['state' => 'close'], 200),
        '*' => Http::response([], 200),
    ]);

    // Incomplete owner — gated from dashboard but /whatsapp is reachable
    $user = User::factory()->notOnboarded()->create();

    $this->actingAs($user)
        ->get('/whatsapp?return=/onboarding')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('return_to', '/onboarding')
        );
});
