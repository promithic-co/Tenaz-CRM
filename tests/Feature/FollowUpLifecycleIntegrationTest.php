<?php

use App\Ai\Agents\CredFlowFollowUpAgent;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\AgentFollowUpSetting;
use App\Models\Lead;
use App\Models\User;
use App\Services\FollowUpSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('atualizar status lead activates follow-up only when qualified inside customer window', function () {
    $lead = Lead::factory()->create([
        'status' => 'novo',
        'followup_status' => 'inactive',
        'followup_count' => 2,
        'last_inbound_at' => now()->subHour(),
    ]);

    $result = (new AtualizarStatusLeadTool($lead))->handle(new Request(['status' => 'qualificado']));

    $lead->refresh();

    expect((string) $result)->toContain('success')
        ->and($lead->status)->toBe('qualificado')
        ->and($lead->followup_status)->toBe('active')
        ->and($lead->followup_count)->toBe(0);
});

test('atualizar status lead does not activate follow-up outside customer window', function () {
    $lead = Lead::factory()->create([
        'status' => 'novo',
        'followup_status' => 'inactive',
        'followup_count' => 2,
        'last_inbound_at' => now()->subHours(25),
    ]);

    (new AtualizarStatusLeadTool($lead))->handle(new Request(['status' => 'qualificado']));

    $lead->refresh();

    expect($lead->status)->toBe('qualificado')
        ->and($lead->followup_status)->toBe('inactive')
        ->and($lead->followup_count)->toBe(0);
});

test('terminal status update removes lead from follow-up queue', function () {
    $lead = Lead::factory()->create([
        'status' => 'qualificado',
        'followup_status' => 'active',
        'followup_count' => 1,
        'last_inbound_at' => now()->subHour(),
    ]);

    (new AtualizarStatusLeadTool($lead))->handle(new Request(['status' => 'optou_sair']));

    $lead->refresh();

    expect($lead->status)->toBe('optou_sair')
        ->and($lead->followup_status)->toBe('inactive')
        ->and($lead->followup_count)->toBe(1);
});

test('inss credit qualification tool is an entrypoint into follow-up queue', function () {
    config(['services.credflow.webhook_consulta' => 'https://n8n.test/webhook/inss']);

    Http::fake([
        'n8n.test/*' => Http::response([
            'status' => 'QUALIFICADO',
            'cliente' => ['nome' => 'JOAO TESTE', 'idade' => 66],
            'resumoGeral' => [
                'totais' => [
                    'margemLivre' => 5200.0,
                    'refinanciamento' => 0,
                    'cartoes' => 0,
                    'totalEstimado' => 5200.0,
                ],
            ],
            'beneficios' => [[
                'produtos' => [
                    'emprestimoNovo' => ['valorLiberado' => 5200.0, 'parcelaMensal' => 120.0],
                    'cartoes' => [],
                ],
            ]],
        ], 200),
    ]);

    $lead = Lead::factory()->create([
        'status' => 'novo',
        'followup_status' => 'inactive',
        'followup_count' => 3,
        'last_inbound_at' => now()->subMinutes(10),
    ]);

    (new ConsultarCreditoInssTool($lead))->handle(new Request(['cpf' => '69747830191']));

    $lead->refresh();

    expect($lead->status)->toBe('qualificado')
        ->and($lead->cpf)->toBe('69747830191')
        ->and($lead->followup_status)->toBe('active')
        ->and($lead->followup_count)->toBe(0);
});

test('follow-up agent instructions use effective follow-up settings', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);

    AgentFollowUpSetting::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $user->tenantId,
        'first_delay_minutes' => 7,
        'min_interval_minutes' => 45,
        'max_attempts_within_window' => 2,
        'business_window_start' => '09:00',
        'business_window_end' => '18:00',
        'message_type' => 'proposta',
        'tone' => 'direto',
        'persuasion_intensity' => 4,
        'custom_instructions' => 'Priorizar valor liberado.',
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'status' => 'qualificado',
        'followup_status' => 'active',
        'followup_count' => 1,
        'last_inbound_at' => now()->subHour(),
    ]);

    $instructions = (string) (new CredFlowFollowUpAgent($lead))->instructions();

    expect($instructions)->toContain('Tentativa 2 de 2')
        ->and($instructions)->toContain('Tipo de mensagem: proposta')
        ->and($instructions)->toContain('Tom de voz: direto')
        ->and($instructions)->toContain('Priorizar valor liberado.');
});

test('agent follow-up update clears scheduler settings cache', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $user->tenantId,
        'followup_first_delay_minutes' => 10,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
    ]);

    $resolver = app(FollowUpSettingsResolver::class);
    expect($resolver->forLead($lead)['first_delay_minutes'])->toBe(10);

    $this->actingAs($user)
        ->post(route('agentes.followup.update', $agent), [
            'first_delay_minutes' => 35,
            'daily_time' => '10:00',
            'max_count' => 3,
            'approach' => 'natural',
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
            'followup_interval_days' => 1,
            'message_type' => 'reengajamento',
            'tone' => 'consultivo',
            'persuasion_intensity' => 2,
            'custom_instructions' => '',
        ])
        ->assertRedirect();

    expect($resolver->forLead($lead)['first_delay_minutes'])->toBe(35);
});
