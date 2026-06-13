<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('test_dashboard_returns_trend_data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats', fn ($stats) => $stats
                ->has('novos_ontem')
                ->has('qualificados_semana')
                ->has('escalados_semana')
                ->has('funnel')
                ->etc()
            )
        );
});

test('test_funnel_counts_are_accurate', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    Lead::factory()->forAgent($agent)->count(3)->create(['status' => 'qualificado']);
    Lead::factory()->forAgent($agent)->count(2)->create(['status' => 'escalado']);
    Lead::factory()->forAgent($agent)->count(2)->create(['status' => 'novo']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats', fn ($stats) => $stats
                ->has('funnel', 3)
                ->where('funnel.0.stage', 'Total')
                ->where('funnel.0.count', 7)
                ->where('funnel.1.stage', 'qualificado')
                ->where('funnel.1.count', 3)
                ->where('funnel.2.stage', 'escalado')
                ->where('funnel.2.count', 2)
                ->etc()
            )
        );
});
