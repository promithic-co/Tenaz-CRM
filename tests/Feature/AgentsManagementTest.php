<?php

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\User;
use App\Models\WhatsappInstance;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user can create agent from available instance', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), [
            'template_slug' => 'alicia-receptivo',
            'agent_niche' => 'inss',
            'name' => 'Agente Especialista',
            'company_name' => 'Amec',
            'description' => 'Especialista em reativação',
            'whatsapp_instance_id' => $instance->id,
        ])
        ->assertRedirect();

    $agent = Agent::query()->where('user_id', $user->id)->where('name', 'Agente Especialista')->first();

    expect($agent)->not->toBeNull();
    expect($instance->fresh()->agent_id)->toBe($agent->id);
    expect(AgentConfig::query()->where('agent_id', $agent->id)->exists())->toBeTrue();
});

test('cannot create agent with instance already attached to another agent', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $instance->update(['agent_id' => $agent->id]);

    $this->actingAs($user)
        ->post(route('agentes.store'), [
            'template_slug' => 'alicia-receptivo',
            'agent_niche' => 'inss',
            'name' => 'Novo Agente',
            'company_name' => 'Amec',
            'whatsapp_instance_id' => $instance->id,
        ])
        ->assertSessionHasErrors('whatsapp_instance_id');
});

test('create agent without whatsapp_instance_id succeeds and agent is inactive', function () {
    $user = userWithTenant();

    $this->actingAs($user)
        ->post(route('agentes.store'), [
            'template_slug' => 'alicia-receptivo',
            'agent_niche' => 'inss',
            'name' => 'Agente Sem Instância',
            'company_name' => 'Empresa Teste',
            'description' => 'Descrição opcional',
            // no whatsapp_instance_id — must be accepted (D-13)
        ])
        ->assertRedirect();

    $agent = \App\Models\Agent::query()
        ->where('user_id', $user->id)
        ->where('name', 'Agente Sem Instância')
        ->first();

    expect($agent)->not->toBeNull();
    expect($agent->is_active)->toBeFalse(); // inactive because no instance linked (T-60-06)
    expect($agent->whatsappInstance)->toBeNull();
});

test('create agent with free owned instance produces active linked agent', function () {
    $user = userWithTenant();
    $instance = \App\Models\WhatsappInstance::factory()->for($user)->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => null,
    ]);

    $this->actingAs($user)
        ->post(route('agentes.store'), [
            'template_slug' => 'alicia-receptivo',
            'agent_niche' => 'inss',
            'name' => 'Agente Com Instância',
            'company_name' => 'Empresa Teste',
            'whatsapp_instance_id' => $instance->id,
        ])
        ->assertRedirect();

    $agent = \App\Models\Agent::query()
        ->where('user_id', $user->id)
        ->where('name', 'Agente Com Instância')
        ->first();

    expect($agent)->not->toBeNull();
    expect($agent->is_active)->toBeTrue(); // active because instance linked successfully (T-60-06)
    expect($instance->fresh()->agent_id)->toBe($agent->id);
});

test('user cannot access config of another tenants agent', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $agentB = Agent::factory()->create(['user_id' => $userB->id]);

    $this->actingAs($userA)
        ->get(route('agentes.config', $agentB))
        ->assertNotFound();
});
