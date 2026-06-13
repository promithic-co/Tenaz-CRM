<?php

use App\Models\AgentConfig;
use App\Models\WhatsappInstance;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// --- Helpers ---

function createAgentPayload(array $overrides = []): array
{
    return array_merge([
        'template_slug' => 'alicia-receptivo',
        'agent_niche' => 'inss',
        'name' => 'Minha Alicia',
        'company_name' => 'Banco Test',
        'description' => null,
    ], $overrides);
}

// --- Template picker: Alicia ---

test('alicia template applies acolhedora personality and correct agent_name', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => 'alicia-receptivo',
            'name' => 'Alicia',
            'company_name' => 'Amec',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config)->not->toBeNull()
        ->and($config->agent_name)->toBe('Alicia')
        ->and($config->company_name)->toBe('Amec')
        ->and($config->agent_personality)->toContain('acolhedora')
        ->and($config->agent_greeting)->toContain('Como posso te ajudar hoje?')
        ->and($config->extra_rules)->toContain('Nunca abra apresentando a financeira')
        ->and($config->max_chars)->toBe(320)
        ->and($config->temperature)->toBe(0.6);
});

// --- Template picker: Tenaz CRM ---

test('tenaz crm template applies direta personality and bulk defaults', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => 'aria-bulk',
            'name' => 'Tenaz CRM',
            'company_name' => 'BMG',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config)->not->toBeNull()
        ->and($config->agent_name)->toBe('Tenaz CRM')
        ->and($config->company_name)->toBe('BMG')
        ->and($config->agent_personality)->toContain('direta')
        ->and($config->max_chars)->toBe(220)
        ->and($config->temperature)->toBe(0.4);
});

// --- template_slug is persisted for traceability ---

test('template_slug is stored in agent_config for traceability', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => 'aria-bulk',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config->template_slug)->toBe('aria-bulk');
});

// --- company_name is saved ---

test('company_name submitted at creation is saved to agent_config', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'company_name' => 'Banco Pan',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config->company_name)->toBe('Banco Pan');
});

test('agent_niche submitted at creation is saved to agent_config', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'agent_niche' => 'clt',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config->agent_niche)->toBe('clt');
});

// --- Validation: template_slug ---

test('missing template_slug returns validation error', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => null,
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertSessionHasErrors('template_slug');
});

test('invalid template_slug returns validation error', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => 'template-que-nao-existe',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertSessionHasErrors('template_slug');
});

test('missing agent_niche returns validation error', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'agent_niche' => null,
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertSessionHasErrors('agent_niche');
});

test('invalid agent_niche returns validation error', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'agent_niche' => 'consorcio',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertSessionHasErrors('agent_niche');
});

// --- Validation: company_name ---

test('missing company_name returns validation error', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'company_name' => null,
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertSessionHasErrors('company_name');
});

// --- Tenant isolation ---

test('agent created by tenant A does not bleed into tenant B', function () {
    $userA = userWithTenant();
    $userB = userWithTenant();
    $instanceA = WhatsappInstance::factory()->for($userA)->create();

    $this->actingAs($userA)
        ->post(route('agentes.store'), createAgentPayload([
            'name' => 'Agente A',
            'company_name' => 'Empresa A',
            'whatsapp_instance_id' => $instanceA->id,
        ]))
        ->assertRedirect();

    expect(AgentConfig::query()->where('tenant_id', $userA->tenantId)->count())->toBe(1);
    expect(AgentConfig::query()->where('tenant_id', $userB->tenantId)->count())->toBe(0);
});

// --- Create page passes templates to the view ---

test('create page passes template options and default to the view', function () {
    $user = userWithTenant();

    $this->actingAs($user)
        ->get(route('agentes.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('agentes/Create')
            ->has('templates', 2)
            ->has('templates.0', fn ($tpl) => $tpl
                ->where('slug', 'alicia-receptivo')
                ->has('name')
                ->has('description')
                ->has('example_first_message')
                ->etc()
            )
            ->where('default_template', 'alicia-receptivo')
            ->has('specializations', 3)
            ->where('default_specialization', 'inss')
        );
});
