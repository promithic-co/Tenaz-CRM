<?php

use App\Actions\SnapshotAgentAsTemplateAction;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\NicheTemplate;
use App\Models\PromptTemplate;
use App\Models\ToolDefinition;
use App\Models\User;
use App\Services\AgentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build an agent with a full configuration (config + prompt + webhook tool)
 * carrying the origin tenant's identity and a secret webhook, ready to snapshot.
 */
function agentToSnapshot(User $user, string $companyName = 'Acme Financeira', string $agentName = 'Sofia'): Agent
{
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'Agente Acme',
    ]);

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $user->tenantId,
        'agent_niche' => 'inss',
        'agent_name' => $agentName,
        'company_name' => $companyName,
        'agent_personality' => "consultiva, representando a {$companyName}",
        'agent_greeting' => "Olá, aqui é a {$agentName} da {$companyName}.",
    ]);

    PromptTemplate::create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'slug' => 'system-acme',
        'name' => 'Sistema',
        'type' => 'system',
        'content' => "Você é {$agentName}, atendente da {$companyName}. Represente bem a {$companyName}.",
        'is_active' => true,
    ]);

    ToolDefinition::create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'slug' => 'consulta-webhook',
        'name' => 'Consulta',
        'description' => 'Consulta externa.',
        'type' => 'webhook',
        'config' => [
            'url' => 'https://api.acme.example/consulta?token=SUPER_SECRET_TOKEN',
            'method' => 'POST',
            'headers' => ['Authorization' => 'Bearer SUPER_SECRET_TOKEN'],
        ],
        'is_active' => true,
    ]);

    return $agent;
}

// --- Snapshot extraction + sanitization ---

test('snapshot strips tenant identity and secrets from the generated template', function () {
    $user = userWithTenant();
    $agent = agentToSnapshot($user);

    $template = app(SnapshotAgentAsTemplateAction::class)->execute($agent, 'Recepção Financeira', 'tenant');

    expect($template->visibility)->toBe('tenant')
        ->and($template->origin_tenant_id)->toBe((string) $user->tenantId)
        ->and($template->is_active)->toBeTrue();

    // Prompt content: identity replaced by placeholders, original never leaks
    $promptContent = $template->prompt_templates[0]['content'];
    expect($promptContent)->toContain('{{agent_name}}')
        ->and($promptContent)->toContain('{{company_name}}')
        ->and($promptContent)->not->toContain('Acme Financeira')
        ->and($promptContent)->not->toContain('Sofia');

    // Webhook secrets: url collapsed to placeholder, headers/token gone
    $toolConfig = $template->tool_definitions[0]['config'];
    expect($toolConfig['url'])->toBe('{{WEBHOOK_URL}}')
        ->and($toolConfig)->not->toHaveKey('headers')
        ->and(json_encode($toolConfig))->not->toContain('SUPER_SECRET_TOKEN');

    // Greeting example also scrubbed
    expect($template->example_first_message)->not->toContain('Acme Financeira')
        ->and($template->example_first_message)->not->toContain('Sofia');

    // default_config carries no raw identity either (leaks on promote-to-system)
    $defaultConfig = json_encode($template->default_config);
    expect($defaultConfig)->not->toContain('Acme Financeira')
        ->and($defaultConfig)->not->toContain('Sofia')
        ->and($template->default_config['agent_greeting'])->toContain('{{company_name}}');

    // Gallery-card fields (shown to every tenant on promote-to-system) also scrubbed:
    // tagline (personality), description (origin agent label), variables_schema placeholder.
    $card = json_encode([$template->tagline, $template->description, $template->variables_schema]);
    expect($card)->not->toContain('Acme Financeira')
        ->and($card)->not->toContain('Agente Acme')
        ->and($template->tagline)->toContain('{{company_name}}');
});

test('snapshot endpoint creates a private template for the owner', function () {
    $user = userWithTenant();
    $agent = agentToSnapshot($user);

    $this->actingAs($user)
        ->post(route('agentes.snapshot', $agent), ['name' => 'Meu Modelo'])
        ->assertRedirect();

    $template = NicheTemplate::query()->where('name', 'Meu Modelo')->first();

    expect($template)->not->toBeNull()
        ->and($template->visibility)->toBe('tenant')
        ->and($template->origin_tenant_id)->toBe((string) $user->tenantId);
});

test('non super-admin owner cannot promote a snapshot to system visibility', function () {
    $user = userWithTenant();
    $agent = agentToSnapshot($user);

    $this->actingAs($user)
        ->post(route('agentes.snapshot', $agent), ['name' => 'Tentativa Sistema', 'visibility' => 'system'])
        ->assertRedirect();

    $template = NicheTemplate::query()->where('name', 'Tentativa Sistema')->first();

    expect($template->visibility)->toBe('tenant');
});

// --- Cross-tenant isolation ---

test('a private snapshot is invisible to another tenant', function () {
    $owner = userWithTenant();
    $agent = agentToSnapshot($owner);
    $template = app(SnapshotAgentAsTemplateAction::class)->execute($agent, 'Modelo Privado', 'tenant');

    $service = app(AgentTemplateService::class);

    // Origin tenant sees it; another tenant does not
    expect(collect($service->all($owner->tenantId))->pluck('slug'))->toContain($template->slug)
        ->and($service->slugs($owner->tenantId))->toContain($template->slug);

    $other = userWithTenant();
    expect(collect($service->all($other->tenantId))->pluck('slug'))->not->toContain($template->slug)
        ->and($service->slugs($other->tenantId))->not->toContain($template->slug);
});

test('another tenant cannot create an agent from a foreign private snapshot slug', function () {
    $owner = userWithTenant();
    $agent = agentToSnapshot($owner);
    $template = app(SnapshotAgentAsTemplateAction::class)->execute($agent, 'Modelo Alheio', 'tenant');

    $other = userWithTenant();

    $this->actingAs($other)
        ->post(route('agentes.store'), [
            'template_slug' => $template->slug,
            'name' => 'Clone Indevido',
            'company_name' => 'Outra Empresa',
        ])
        ->assertSessionHasErrors('template_slug');

    expect(Agent::query()->where('tenant_id', $other->tenantId)->exists())->toBeFalse();
});

// --- Replication (apply a snapshot) ---

test('applying a snapshot in the same tenant creates a functional agent with placeholder webhook', function () {
    $user = userWithTenant();
    $agent = agentToSnapshot($user);
    $template = app(SnapshotAgentAsTemplateAction::class)->execute($agent, 'Modelo Replicável', 'tenant');

    $this->actingAs($user)
        ->post(route('agentes.store'), [
            'template_slug' => $template->slug,
            'name' => 'Agente Nova Empresa',
            'company_name' => 'Nova Empresa LTDA',
        ])
        ->assertRedirect();

    $newAgent = Agent::query()
        ->where('tenant_id', $user->tenantId)
        ->where('name', 'Agente Nova Empresa')
        ->first();

    expect($newAgent)->not->toBeNull();

    $newConfig = AgentConfig::query()->where('agent_id', $newAgent->id)->first();
    expect($newConfig->agent_niche)->toBe('inss')
        ->and($newConfig->company_name)->toBe('Nova Empresa LTDA')
        ->and($newConfig->template_slug)->toBe($template->slug);

    // Applied prompt template + webhook (with placeholder url that must be re-entered)
    $appliedTool = ToolDefinition::query()
        ->where('tenant_id', $user->tenantId)
        ->where('agent_id', $newAgent->id)
        ->where('slug', 'consulta-webhook')
        ->first();

    expect($appliedTool)->not->toBeNull()
        ->and($appliedTool->config['url'])->toBe('{{WEBHOOK_URL}}');

    $appliedPrompt = PromptTemplate::query()
        ->where('tenant_id', $user->tenantId)
        ->where('agent_id', $newAgent->id)
        ->where('slug', 'system-acme')
        ->first();

    expect($appliedPrompt)->not->toBeNull()
        ->and($appliedPrompt->content)->toContain('{{company_name}}');
});
