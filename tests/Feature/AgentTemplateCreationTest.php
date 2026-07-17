<?php

use App\Models\AgentConfig;
use App\Models\Lead;
use App\Models\NicheTemplate;
use App\Models\PromptTemplate;
use App\Models\StatusMachine;
use App\Models\ToolDefinition;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// --- Helpers ---

function createAgentPayload(array $overrides = []): array
{
    return array_merge([
        'template_slug' => 'alicia-receptivo',
        'name' => 'Minha Alicia',
        'company_name' => 'Banco Test',
        'description' => null,
    ], $overrides);
}

function wizardTemplate(array $overrides = []): NicheTemplate
{
    return NicheTemplate::factory()->create(array_merge([
        'slug' => 'clinica-recepcao',
        'name' => 'Sofia',
        'variables_schema' => [
            ['key' => 'agent_name', 'label' => 'Nome do agente', 'type' => 'text', 'required' => true, 'max' => 100],
            ['key' => 'company_name', 'label' => 'Nome da empresa', 'type' => 'text', 'required' => true, 'max' => 100],
            ['key' => 'personality_block', 'label' => 'Personalidade', 'type' => 'textarea', 'required' => false, 'max' => 1000],
            ['key' => 'extra_rules', 'label' => 'Regras da operação', 'type' => 'textarea', 'required' => true, 'max' => 2000],
        ],
    ], $overrides));
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

// --- Niche is a template attribute, never user input ---

test('agent_niche is derived from the template defaults, not from the request', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'agent_niche' => 'clt',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config->agent_niche)->toBe('inss');
});

test('templates without a niche in defaults fall back to generic', function () {
    wizardTemplate([
        'variables_schema' => null,
        'default_config' => ['agent_name' => 'Sofia'],
    ]);

    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => 'clinica-recepcao',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config->agent_niche)->toBe('generic');
});

// --- Dynamic variables from variables_schema ---

test('required template variable missing returns validation error', function () {
    wizardTemplate();

    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => 'clinica-recepcao',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertSessionHasErrors('variables.extra_rules');
});

test('wizard variables customize whitelisted agent_config columns', function () {
    wizardTemplate();

    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => 'clinica-recepcao',
            'whatsapp_instance_id' => $instance->id,
            'variables' => [
                'personality_block' => 'Fale como recepcionista experiente.',
                'extra_rules' => '- Não atender convênio X.',
            ],
        ]))
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config->agent_personality)->toBe('Fale como recepcionista experiente.')
        ->and($config->extra_rules)->toBe('- Não atender convênio X.');
});

test('variables outside the schema or whitelist never reach agent_config', function () {
    wizardTemplate();

    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => 'clinica-recepcao',
            'whatsapp_instance_id' => $instance->id,
            'variables' => [
                'extra_rules' => '- Regra legítima.',
                'agent_model' => 'gpt-hackado',
                'tenant_id' => 'outro-tenant',
            ],
        ]))
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config->agent_model)->not->toBe('gpt-hackado')
        ->and($config->tenant_id)->toBe($user->tenantId);
});

// --- Template resources are applied to the tenant on creation ---

test('creating an agent from a registry template applies its prompts and tools to the tenant', function () {
    wizardTemplate([
        'variables_schema' => null,
        'prompt_templates' => [
            ['slug' => 'clinica-system', 'name' => 'Clínica — Prompt', 'type' => 'system', 'content' => 'Você é {{agent_name}}.'],
        ],
        'tool_definitions' => [
            ['slug' => 'agendar_consulta', 'name' => 'Agendar Consulta', 'type' => 'webhook', 'config' => ['url' => 'https://example.test/hook']],
        ],
    ]);

    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), createAgentPayload([
            'template_slug' => 'clinica-recepcao',
            'whatsapp_instance_id' => $instance->id,
        ]))
        ->assertRedirect();

    $agentId = AgentConfig::query()->where('tenant_id', $user->tenantId)->first()->agent_id;

    expect(PromptTemplate::withoutGlobalScopes()->where('tenant_id', $user->tenantId)->where('agent_id', $agentId)->where('slug', 'clinica-system')->exists())->toBeTrue()
        ->and(ToolDefinition::withoutGlobalScopes()->where('tenant_id', $user->tenantId)->where('agent_id', $agentId)->where('slug', 'agendar_consulta')->exists())->toBeTrue();
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
                ->has('category')
                ->has('variables_schema')
                ->has('example_first_message')
                ->etc()
            )
            ->where('default_template', 'alicia-receptivo')
            ->missing('specializations')
        );
});

test('create page default_template falls back to first registry slug when config default is absent', function () {
    wizardTemplate(['sort_order' => 0]);

    $user = userWithTenant();

    $this->actingAs($user)
        ->get(route('agentes.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('agentes/Create')
            ->has('templates', 1)
            ->where('default_template', 'clinica-recepcao')
        );
});

// --- Status machine guard (Slice 5) ---

test('template apply is blocked when production leads sit on statuses missing from the new machine', function () {
    $template = wizardTemplate([
        'status_machine' => [
            'initial_status' => 'lead',
            'statuses' => [
                ['slug' => 'lead', 'label' => 'Lead', 'color' => 'gray', 'is_terminal' => false],
                ['slug' => 'fechado', 'label' => 'Fechado', 'color' => 'purple', 'is_terminal' => true],
            ],
            'transitions' => [['from' => 'lead', 'to' => 'fechado']],
        ],
    ]);

    Lead::factory()->create([
        'tenant_id' => 'tenant-guard',
        'status' => 'qualificado',
        'is_sandbox' => false,
    ]);

    expect(fn () => $template->apply('tenant-guard'))
        ->toThrow(ValidationException::class, 'qualificado');
});

test('template apply proceeds when stranded leads are sandbox-only or statuses are covered', function () {
    $template = wizardTemplate([
        'status_machine' => [
            'initial_status' => 'lead',
            'statuses' => [
                ['slug' => 'lead', 'label' => 'Lead', 'color' => 'gray', 'is_terminal' => false],
                ['slug' => 'fechado', 'label' => 'Fechado', 'color' => 'purple', 'is_terminal' => true],
            ],
            'transitions' => [['from' => 'lead', 'to' => 'fechado']],
        ],
    ]);

    Lead::factory()->create([
        'tenant_id' => 'tenant-guard-ok',
        'status' => 'qualificado',
        'is_sandbox' => true,
    ]);
    Lead::factory()->create([
        'tenant_id' => 'tenant-guard-ok',
        'status' => 'lead',
        'is_sandbox' => false,
    ]);

    $template->apply('tenant-guard-ok');

    expect(StatusMachine::query()->where('tenant_id', 'tenant-guard-ok')->exists())->toBeTrue();
});
