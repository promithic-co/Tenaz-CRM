<?php

use App\Models\AgentConfig;
use App\Models\NicheTemplate;
use App\Models\WhatsappInstance;
use App\Services\AgentTemplateService;
use Database\Seeders\NicheTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function templateService(): AgentTemplateService
{
    return app(AgentTemplateService::class);
}

// --- Fallback: empty registry keeps config-file behavior ---

test('service falls back to config file when registry table is empty', function () {
    $templates = templateService()->all();

    expect($templates)->toHaveCount(2)
        ->and(collect($templates)->pluck('slug')->all())
        ->toBe(array_keys(config('agent_templates.templates')));
});

test('defaults fall back to config file when registry table is empty', function () {
    $defaults = templateService()->defaults('alicia-receptivo');

    expect($defaults['agent_name'])->toBe('Alicia')
        ->and($defaults['max_chars'])->toBe(320);
});

// --- DB-first resolution ---

test('registry rows take precedence over config file templates', function () {
    NicheTemplate::factory()->create(['slug' => 'clinica-recepcao', 'name' => 'Sofia']);

    $templates = templateService()->all();

    expect($templates)->toHaveCount(1)
        ->and($templates[0]['slug'])->toBe('clinica-recepcao')
        ->and($templates[0]['name'])->toBe('Sofia')
        ->and($templates[0])->toHaveKeys([
            'slug', 'name', 'label', 'description', 'category', 'tagline',
            'icon', 'mode', 'use_cases', 'example_first_message', 'variables_schema',
        ]);
});

test('inactive registry rows are excluded from gallery and slugs', function () {
    NicheTemplate::factory()->create(['slug' => 'ativo']);
    NicheTemplate::factory()->inactive()->create(['slug' => 'oculto']);

    expect(collect(templateService()->all())->pluck('slug')->all())->toBe(['ativo'])
        ->and(templateService()->slugs())->toBe(['ativo']);
});

test('gallery respects sort_order', function () {
    NicheTemplate::factory()->create(['slug' => 'segundo', 'sort_order' => 10]);
    NicheTemplate::factory()->create(['slug' => 'primeiro', 'sort_order' => 0]);

    expect(collect(templateService()->all())->pluck('slug')->all())
        ->toBe(['primeiro', 'segundo']);
});

test('defaults come from registry default_config when row exists', function () {
    NicheTemplate::factory()->create([
        'slug' => 'clinica-recepcao',
        'default_config' => ['agent_name' => 'Sofia', 'max_chars' => 280, 'temperature' => 0.5],
    ]);

    $defaults = templateService()->defaults('clinica-recepcao');

    expect($defaults['agent_name'])->toBe('Sofia')
        ->and($defaults['max_chars'])->toBe(280);
});

// --- Cache invalidation (observer-busted single key) ---

test('registry cache is busted when a template is saved', function () {
    $template = NicheTemplate::factory()->create(['slug' => 'card', 'name' => 'Antes']);

    expect(templateService()->all()[0]['name'])->toBe('Antes');

    $template->update(['name' => 'Depois']);

    expect(templateService()->all()[0]['name'])->toBe('Depois');
});

test('registry cache is busted when a template is deleted', function () {
    $template = NicheTemplate::factory()->create(['slug' => 'card']);

    expect(templateService()->all())->toHaveCount(1);

    $template->delete();

    expect(templateService()->all())->toHaveCount(2); // config fallback reappears
});

// --- End-to-end: agent creation consumes registry defaults ---

test('storing an agent with a registry template applies its default_config', function () {
    NicheTemplate::factory()->create([
        'slug' => 'clinica-recepcao',
        'default_config' => [
            'agent_name' => 'Sofia',
            'agent_personality' => 'atenciosa e organizada',
            'max_chars' => 280,
            'temperature' => 0.5,
        ],
    ]);

    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), [
            'template_slug' => 'clinica-recepcao',
            'agent_niche' => 'inss',
            'name' => 'Sofia',
            'company_name' => 'Clínica Vida',
            'description' => null,
            'whatsapp_instance_id' => $instance->id,
        ])
        ->assertRedirect();

    $config = AgentConfig::query()->where('tenant_id', $user->tenantId)->first();

    expect($config)->not->toBeNull()
        ->and($config->template_slug)->toBe('clinica-recepcao')
        ->and($config->agent_personality)->toBe('atenciosa e organizada')
        ->and($config->max_chars)->toBe(280)
        ->and($config->temperature)->toBe(0.5);
});

test('storing an agent with an inactive registry template is rejected', function () {
    NicheTemplate::factory()->create(['slug' => 'ativo']);
    NicheTemplate::factory()->inactive()->create(['slug' => 'oculto']);

    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('agentes.store'), [
            'template_slug' => 'oculto',
            'agent_niche' => 'inss',
            'name' => 'Agente',
            'company_name' => 'Empresa',
            'description' => null,
            'whatsapp_instance_id' => $instance->id,
        ])
        ->assertSessionHasErrors('template_slug');
});

// --- Seeder: creation cards mirror config templates ---

test('seeder creates active cards for config templates and hides infra templates', function () {
    $this->seed(NicheTemplateSeeder::class);

    $cards = collect(templateService()->all());

    expect($cards->pluck('slug')->all())
        ->toBe(['alicia-receptivo', 'aria-bulk'])
        ->and(NicheTemplate::query()->where('slug', 'corban-simulador')->first()->is_active)->toBeFalse()
        ->and(NicheTemplate::query()->where('slug', 'inss-consignado')->first()->is_active)->toBeFalse()
        ->and(NicheTemplate::query()->where('slug', 'imobiliario')->first()->is_active)->toBeFalse();

    $alicia = $cards->firstWhere('slug', 'alicia-receptivo');
    $configTemplate = config('agent_templates.templates.alicia-receptivo');

    expect($alicia['name'])->toBe($configTemplate['name'])
        ->and($alicia['label'])->toBe($configTemplate['label'])
        ->and($alicia['mode'])->toBe($configTemplate['mode'])
        ->and(templateService()->defaults('alicia-receptivo'))->toBe($configTemplate['defaults']);
});

test('seeded corban template carries the v3.3 system prompt with platform variables', function () {
    $this->seed(NicheTemplateSeeder::class);

    $corban = NicheTemplate::query()->where('slug', 'corban-simulador')->first();
    $prompt = $corban->prompt_templates[0];

    expect($prompt['type'])->toBe('system')
        ->and($prompt['content'])->toContain('{{agent_name}}')
        ->and($prompt['content'])->toContain('{{company_name}}')
        ->and($prompt['content'])->toContain('{{personality_block}}')
        ->and($prompt['content'])->not->toContain('{{promotora_name}}')
        ->and($prompt['content'])->toContain('FERRAMENTAS PERMITIDAS (allowlist fechada)');
});
