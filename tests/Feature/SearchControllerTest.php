<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('test_search_requires_auth', function () {
    $this->get(route('search', ['q' => 'Carlos']))
        ->assertRedirect('/login');
});

test('test_search_returns_matching_leads', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    Lead::factory()->forAgent($agent)->create(['nome' => 'Carlos Andrade']);
    Lead::factory()->forAgent($agent)->create(['nome' => 'Maria Silva']);

    $response = $this->actingAs($user)
        ->getJson(route('search', ['q' => 'Carlos']));

    $response->assertOk()
        ->assertJsonPath('leads.0.nome', 'Carlos Andrade')
        ->assertJsonCount(1, 'leads');
});

test('test_search_scopes_to_tenant', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $agentOwn = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $agentOther = Agent::factory()->create(['user_id' => $otherUser->id, 'is_default' => true]);

    Lead::factory()->forAgent($agentOwn)->create(['nome' => 'Carlos Próprio']);
    Lead::factory()->forAgent($agentOther)->create(['nome' => 'Carlos Outro Tenant']);

    $response = $this->actingAs($user)
        ->getJson(route('search', ['q' => 'Carlos']));

    $response->assertOk()
        ->assertJsonCount(1, 'leads')
        ->assertJsonPath('leads.0.nome', 'Carlos Próprio');
});

test('test_search_returns_empty_for_short_query', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson(route('search', ['q' => 'C']));

    $response->assertOk()
        ->assertJsonPath('leads', [])
        ->assertJsonPath('agents', []);
});
