<?php

use App\Models\Lead;
use App\Models\User;

it('returns JSON data and next cursor for a valid status slug', function () {
    $user = User::factory()->create();

    Lead::factory()->count(35)->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
    ]);

    $response = $this->actingAs($user)->getJson('/pipeline/columns/novo');

    $response->assertOk()
        ->assertJsonStructure(['data', 'next_cursor'])
        ->assertJsonCount(30, 'data');

    expect($response->json('next_cursor'))->not->toBeNull();
});

it('aborts 404 for unknown status slug', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/pipeline/columns/unknown-slug-xyz')
        ->assertNotFound();
});

it('respects search filters when paginating', function () {
    $user = User::factory()->create();

    Lead::factory()->count(5)->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
        'nome' => 'Alpha Lead',
    ]);
    Lead::factory()->count(5)->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
        'nome' => 'Beta Lead',
    ]);

    $response = $this->actingAs($user)->getJson('/pipeline/columns/novo?search=Alpha');

    $response->assertOk();

    expect(collect($response->json('data'))->pluck('nome')->all())
        ->each->toContain('Alpha');
});
