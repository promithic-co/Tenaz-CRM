<?php

use App\Models\Agent;
use App\Models\AgentOperationalRule;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Page access ─────────────────────────────────────────────────────────────

test('guests cannot access regras operacionais', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);

    $this->get(route('agentes.regras-operacionais', $agent))->assertRedirect(route('login'));
});

test('authenticated users can access regras operacionais', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);

    $this->actingAs($user)
        ->get(route('agentes.regras-operacionais', $agent))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('regras-operacionais/Index')
            ->has('agent')
            ->has('rules')
            ->has('rules.instituicoes_config')
            ->has('rules.regras_globais')
            ->has('rules.regras_especies')
        );
});

// ─── Institution names displayed ─────────────────────────────────────────────

test('index returns institution names from canonical list when DB has none', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);

    AgentOperationalRule::create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'instituicoes_config' => [
            ['sigla' => 'BMG', 'ativo' => true, 'produtos' => ['novo' => true, 'refin' => true, 'port' => true, 'rmc' => true, 'rcc' => true]],
            ['sigla' => 'C6', 'ativo' => false, 'produtos' => ['novo' => false, 'refin' => false, 'port' => false, 'rmc' => false, 'rcc' => false]],
        ],
        'regras_globais' => AgentOperationalRule::$REGRAS_GLOBAIS_PADRAO,
        'regras_especies' => AgentOperationalRule::$REGRAS_ESPECIES_PADRAO,
    ]);

    $this->actingAs($user)
        ->get(route('agentes.regras-operacionais', $agent))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rules.instituicoes_config', fn ($config) => collect($config)->firstWhere('sigla', 'BMG')['nome'] === 'Banco BMG'
                && collect($config)->firstWhere('sigla', 'C6')['nome'] === 'C6 Bank')
        );
});

test('update preserves institution names when saving', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);
    $rule = AgentOperationalRule::forUser($user->id);

    $payload = [
        'instituicoes_config' => collect($rule->instituicoes_config)->take(2)->map(fn ($b) => [
            'sigla' => $b['sigla'],
            'ativo' => $b['ativo'],
            'produtos' => $b['produtos'],
        ])->values()->all(),
        'regras_globais' => $rule->regras_globais,
        'regras_especies' => $rule->regras_especies,
    ];

    $this->actingAs($user)
        ->put(route('agentes.regras-operacionais.update', $agent), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $rule->refresh();
    $first = collect($rule->instituicoes_config)->firstWhere('sigla', 'BMG');

    expect($first)->not->toBeNull();
    expect($first['nome'])->toBe('Banco BMG');
    expect($first['codigo'])->toBe('318');
});

test('other tenant cannot access agent regras operacionais', function () {
    $user = User::factory()->create();
    $otherAgent = Agent::factory()->create(); // different tenant

    $this->actingAs($user)
        ->get(route('agentes.regras-operacionais', $otherAgent))
        ->assertNotFound();
});
