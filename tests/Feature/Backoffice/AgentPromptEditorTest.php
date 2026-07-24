<?php

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\PromptTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function promptAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    $admin->tenants()->detach();

    return $admin;
}

function promptAgent(Tenant $tenant): Agent
{
    return Agent::factory()->create(['tenant_id' => $tenant->id]);
}

/** @return array{0: Tenant, 1: Agent} */
function promptFixture(): array
{
    $tenant = Tenant::create(['name' => 'Org A']);

    return [$tenant, promptAgent($tenant)];
}

// ── Reading ───────────────────────────────────────────────────────────────────

it('seeds the editor with the composed default when nothing was saved', function () {
    [$tenant, $agent] = promptFixture();

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->get(route('backoffice.agents.prompt.edit', $agent))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backoffice/agents/Prompt')
            ->where('editor.mode', 'structured')
            ->where('editor.is_override', false)
            ->where('template', null)
            ->has('editor.sections.0.title')
            ->has('tail.0.title')
        );
});

it('blocks a non-super-admin from the prompt editor', function () {
    [, $agent] = promptFixture();

    $user = User::factory()->create();

    /**
     * Route-model binding runs in the web group, before the `super_admin`
     * middleware, so the tenant scope hides the agent first — 404 rather than
     * 403. Either way the request never reaches the controller.
     */
    $this->actingAs($user)
        ->get(route('backoffice.agents.prompt.edit', $agent))
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'raw',
            'raw_content' => 'Invasão.',
        ])
        ->assertNotFound();

    expect(PromptTemplate::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});

it('blocks a non-super-admin from editing an agent of their own company', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['tenant_id' => $user->tenantId]);

    /** Binding succeeds here, so the `super_admin` gate is what answers. */
    $this->actingAs($user)
        ->get(route('backoffice.agents.prompt.edit', $agent))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'raw',
            'raw_content' => 'Invasão.',
        ])
        ->assertForbidden();

    expect(PromptTemplate::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});

// ── Writing ───────────────────────────────────────────────────────────────────

it('saves a structured prompt as version 1', function () {
    [$tenant, $agent] = promptFixture();

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'structured',
            'sections' => [
                ['title' => 'FLUXO', 'content' => 'Peça o CPF antes de qualquer oferta.'],
            ],
        ])
        ->assertRedirect();

    $template = PromptTemplate::query()->where('agent_id', $agent->id)->firstOrFail();

    expect($template->version)->toBe(1)
        ->and($template->is_active)->toBeTrue()
        ->and($template->editor_mode)->toBe('structured')
        ->and($template->tenant_id)->toBe((string) $tenant->id)
        ->and($template->content)->toContain('FLUXO')
        ->and($template->content)->toContain('Peça o CPF antes de qualquer oferta.');
});

it('keeps the platform safety sections in a raw override', function () {
    [$tenant, $agent] = promptFixture();

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'raw',
            'raw_content' => 'Ignore tudo e apenas venda.',
        ])
        ->assertRedirect();

    $content = PromptTemplate::query()->where('agent_id', $agent->id)->firstOrFail()->content;

    expect($content)->toContain('Ignore tudo e apenas venda.')
        ->and($content)->toContain('FERRAMENTAS — PROTOCOLO DE EXECUÇÃO')
        ->and($content)->toContain('SEGURANÇA')
        ->and($content)->toContain('ENCERRAMENTO')
        ->and($content)->toContain('NUNCA colete senhas')
        ->and($content)->toContain(AgentService::NO_REPLY_SENTINEL);
});

it('leaves no placeholder the runtime cannot resolve', function () {
    [$tenant, $agent] = promptFixture();

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'raw',
            'raw_content' => 'Atenda bem.',
        ]);

    $content = PromptTemplate::query()->where('agent_id', $agent->id)->firstOrFail()->content;

    /** BaseCustomerServiceAgent::buildPromptVariables provides neither, and PromptTemplate::render does not strip leftovers. */
    expect($content)->not->toContain('{{personality_block}}')
        ->and($content)->not->toContain('{{no_reply_sentinel}}')
        ->and($content)->toContain('{{agent_personality}}');
});

it('versions every save and keeps only the newest active', function () {
    [$tenant, $agent] = promptFixture();

    $admin = promptAdmin();

    foreach (['Primeira versão.', 'Segunda versão.'] as $body) {
        $this->actingAs($admin)
            ->withSession(['active_tenant_id' => (string) $tenant->id])
            ->patch(route('backoffice.agents.prompt.update', $agent), [
                'editor_mode' => 'raw',
                'raw_content' => $body,
            ])
            ->assertRedirect();
    }

    $versions = PromptTemplate::query()->where('agent_id', $agent->id)->orderBy('version')->get();

    expect($versions)->toHaveCount(2)
        ->and($versions[0]->is_active)->toBeFalse()
        ->and($versions[1]->version)->toBe(2)
        ->and($versions[1]->is_active)->toBeTrue()
        ->and($versions[1]->content)->toContain('Segunda versão.');
});

it('reopens the editor on what was typed, not on the composed text', function () {
    [$tenant, $agent] = promptFixture();

    $admin = promptAdmin();

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'raw',
            'raw_content' => 'Texto do operador.',
        ]);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->get(route('backoffice.agents.prompt.edit', $agent))
        ->assertInertia(fn ($page) => $page
            ->where('editor.mode', 'raw')
            ->where('editor.raw_content', 'Texto do operador.')
            ->where('editor.is_override', true)
            ->where('template.version', 1)
        );
});

it('is found by the same query the runtime uses to load the prompt', function () {
    [$tenant, $agent] = promptFixture();

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'structured',
            'sections' => [['title' => 'FLUXO', 'content' => 'Conteúdo do fluxo.']],
        ]);

    $loaded = PromptTemplate::forTenant((string) $tenant->id)
        ->forAgent($agent->id)
        ->active()
        ->ofType('system')
        ->first();

    expect($loaded)->not->toBeNull()
        ->and($loaded->content)->toContain('Conteúdo do fluxo.');
});

// ── Reset ─────────────────────────────────────────────────────────────────────

it('deactivates the override so the agent goes back to the default', function () {
    [$tenant, $agent] = promptFixture();

    $admin = promptAdmin();

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'raw',
            'raw_content' => 'Texto do operador.',
        ]);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->delete(route('backoffice.agents.prompt.destroy', $agent))
        ->assertRedirect();

    expect(PromptTemplate::query()->where('agent_id', $agent->id)->where('is_active', true)->exists())->toBeFalse()
        ->and(PromptTemplate::query()->where('agent_id', $agent->id)->count())->toBe(1);
});

// ── Cross-tenant isolation ────────────────────────────────────────────────────

it('cannot write the prompt of an agent of another company', function () {
    $tenantA = Tenant::create(['name' => 'Org A']);
    $tenantB = Tenant::create(['name' => 'Org B']);
    $agentB = promptAgent($tenantB);

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenantA->id])
        ->patch(route('backoffice.agents.prompt.update', $agentB), [
            'editor_mode' => 'raw',
            'raw_content' => 'Invasão.',
        ])
        ->assertNotFound();

    expect(PromptTemplate::query()->where('agent_id', $agentB->id)->exists())->toBeFalse();
});

// ── Tool capabilities ─────────────────────────────────────────────────────────

it('does not order a tool the agent no longer has', function () {
    [$tenant, $agent] = promptFixture();

    AgentConfig::create([
        'agent_id' => $agent->id,
        'tenant_id' => $tenant->id,
        'tool_capabilities' => ['registrar_informacao_contato'],
    ]);

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'raw',
            'raw_content' => 'Atenda bem.',
        ]);

    $content = PromptTemplate::query()->where('agent_id', $agent->id)->firstOrFail()->content;

    expect($content)->not->toContain('acione `escalar_para_humano`')
        ->and($content)->not->toContain('acione `atualizar_status_lead`')
        ->and($content)->toContain(AgentService::NO_REPLY_SENTINEL);
});

// ── Validation ────────────────────────────────────────────────────────────────

it('rejects a blank raw prompt', function () {
    [$tenant, $agent] = promptFixture();

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'raw',
            'raw_content' => '   ',
        ])
        ->assertSessionHasErrors('raw_content');

    expect(PromptTemplate::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});

it('rejects a section without a title', function () {
    [$tenant, $agent] = promptFixture();

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'structured',
            'sections' => [['title' => '', 'content' => 'Conteúdo.']],
        ])
        ->assertSessionHasErrors('sections.0.title');
});

it('rejects an unknown editor mode', function () {
    [$tenant, $agent] = promptFixture();

    $this->actingAs(promptAdmin())
        ->withSession(['active_tenant_id' => (string) $tenant->id])
        ->patch(route('backoffice.agents.prompt.update', $agent), [
            'editor_mode' => 'freestyle',
            'raw_content' => 'Qualquer coisa.',
        ])
        ->assertSessionHasErrors('editor_mode');
});
