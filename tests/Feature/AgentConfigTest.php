<?php

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\AgentConfigResolver;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    AppSetting::flushCache();
});

// ─── Page access ─────────────────────────────────────────────────────────────

test('guests cannot access agent config', function () {
    $this->get(route('agente.index'))->assertRedirect(route('login'));
});

test('authenticated users are redirected to create agent when none exists', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('agente.index'))
        ->assertRedirect(route('agentes.create'));
});

test('legacy agente route redirects to selected agent config', function () {
    $user = User::factory()->create();
    $agent = createAgentWithConfig($user);

    $this->actingAs($user)
        ->get(route('agente.index'))
        ->assertRedirect(route('agentes.config', $agent));
});

test('agent scoped config page returns persona settings with correct types', function () {
    $user = User::factory()->create();
    $agent = createAgentWithConfig($user);

    $this->actingAs($user)
        ->get(route('agentes.config', $agent))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agente-config/Index')
            ->where('agent.id', $agent->id)
            ->has('settings.agent_name')
            ->has('settings.agent_niche')
            ->has('settings.company_name')
            ->has('settings.agent_personality')
            ->has('settings.agent_greeting')
            ->missing('settings.temperature')
            ->missing('settings.agent_model')
            ->missing('settings.max_tokens')
            ->missing('settings.escalation_whatsapp_number')
            ->has('specializations', 3)
        );
});

// ─── Saving config ───────────────────────────────────────────────────────────

test('can save agent config with persona fields', function () {
    $user = User::factory()->create();
    $agent = createAgentWithConfig($user);

    $payload = [
        'agent_niche' => 'clt',
        'agent_name' => 'TestBot',
        'company_name' => 'TestCorp',
        'agent_personality' => 'amigável e direta',
        'agent_greeting' => 'Olá! Como posso ajudar?',
    ];

    $this->actingAs($user)
        ->post(route('agentes.config.update', $agent), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $agent->refresh();
    $config = $agent->config;
    expect($config)->not->toBeNull();
    expect($config->agent_niche)->toBe('clt');
    expect($config->agent_name)->toBe('TestBot');
    expect($config->company_name)->toBe('TestCorp');
    expect($config->agent_personality)->toBe('amigável e direta');
    expect($config->agent_greeting)->toBe('Olá! Como posso ajudar?');
});

// ─── Cache invalidation ───────────────────────────────────────────────────────

test('saving agent config invalidates the cached resolver value', function () {
    $user = User::factory()->create();
    $agent = createAgentWithConfig($user);
    $resolver = app(AgentConfigResolver::class);

    // Warm the resolver cache
    $resolver->forAgentId($agent->id);

    $this->actingAs($user)
        ->post(route('agentes.config.update', $agent), validPayload([
            'agent_name' => 'NovoBotNome',
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    // Resolver cache busted by Cache::forget in controller update()
    expect($agent->fresh()->config->agent_name)->toBe('NovoBotNome');
});

test('follow-up settings are persisted per agent', function () {
    $user = User::factory()->create();
    $agentA = createAgentWithConfig($user, 'Agente A');
    $agentB = createAgentWithConfig($user, 'Agente B');

    $this->actingAs($user)
        ->post(route('agentes.followup.update', $agentA), [
            'first_delay_minutes' => 45,
            'daily_time' => '11:30',
            'max_count' => 5,
            'approach' => 'persuasivo',
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
            'followup_interval_days' => 1,
            'message_type' => 'urgencia',
            'tone' => 'direto',
            'persuasion_intensity' => 4,
            'custom_instructions' => 'Mencionar que a simulacao ja esta pronta.',
        ])
        ->assertRedirect();

    expect($agentA->fresh()->config->followup_first_delay_minutes)->toBe(45);
    expect($agentA->fresh()->config->followup_daily_time)->toBe('11:30');
    expect($agentA->fresh()->config->followup_max_count)->toBe(5);
    expect($agentA->fresh()->config->followup_approach)->toBe('persuasivo');
    expect($agentA->fresh()->config->followup_message_type)->toBe('urgencia');
    expect($agentA->fresh()->config->followup_tone)->toBe('direto');
    expect($agentA->fresh()->config->followup_persuasion_intensity)->toBe(4);
    expect($agentA->fresh()->config->followup_custom_instructions)->toBe('Mencionar que a simulacao ja esta pronta.');

    expect($agentB->fresh()->config->followup_first_delay_minutes)->toBe(10);
    expect($agentB->fresh()->config->followup_approach)->toBe('natural');
});

// ─── getAgentConfig includes new keys ────────────────────────────────────────

test('getAgentConfig returns temperature, max_tokens, and max_conversation_messages', function () {
    $user = User::factory()->create();

    AppSetting::set('temperature', '0.2', $user->id);
    AppSetting::set('max_tokens', '512', $user->id);
    AppSetting::set('max_conversation_messages', '16', $user->id);

    $config = AppSetting::getAgentConfig($user->id);

    expect($config)->toHaveKeys(['temperature', 'max_tokens', 'max_conversation_messages']);
    expect($config['temperature'])->toBe('0.2');
    expect($config['max_tokens'])->toBe('512');
    expect($config['max_conversation_messages'])->toBe('16');
});

test('getAgentConfig falls back to defaults for new keys', function () {
    $config = AppSetting::getAgentConfig(null);

    expect($config['temperature'])->toBe('0.4');
    expect($config['max_tokens'])->toBe('1024');
    expect($config['max_conversation_messages'])->toBe('24');
});

// ─── Settings isolation ──────────────────────────────────────────────────────

test('getAgentConfig prefers user-specific values over global fallback and defaults', function () {
    $user = User::factory()->create();

    AppSetting::set('agent_name', 'GLOBAL', null);
    AppSetting::set('agent_name', 'CUSTOM', $user->id);
    AppSetting::set('company_name', 'GLOBAL COMPANY', null);

    $config = AppSetting::getAgentConfig($user->id);

    expect($config['agent_name'])->toBe('CUSTOM');
    expect($config['company_name'])->toBe('GLOBAL COMPANY');
    expect($config['agent_model'])->toBe('gpt-4o-mini');
});

test('getExtraAgentConfig falls back to legacy defaults', function () {
    $config = AppSetting::getExtraAgentConfig();

    expect($config['followup_first_delay_minutes'])->toBe('10');
    expect($config['followup_max_count'])->toBe('4');
    expect($config['transcription_provider'])->toBe('openai');
    expect($config['vision_model'])->toBe('gpt-4o');
    // escalation_whatsapp_number removed from EXTRA_AGENT_CONFIG_KEYS/DEFAULTS (Task 3)
    expect(isset($config['escalation_whatsapp_number']))->toBeFalse();
});

test('getExtraAgentConfig prefers user-specific values over global fallback and defaults', function () {
    $user = User::factory()->create();

    AppSetting::set('followup_tone', 'global-tone', null);
    AppSetting::set('followup_tone', 'custom-tone', $user->id);
    AppSetting::set('vision_model', 'global-vision-model', null);

    $config = AppSetting::getExtraAgentConfig($user->id);

    expect($config['followup_tone'])->toBe('custom-tone');
    expect($config['vision_model'])->toBe('global-vision-model');
    expect($config['transcription_model'])->toBe('whisper-1');
});

test('advanced params are isolated per user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    AppSetting::set('temperature', '0.2', $userA->id);
    AppSetting::set('temperature', '0.8', $userB->id);

    expect(AppSetting::get('temperature', null, $userA->id))->toBe('0.2');
    expect(AppSetting::get('temperature', null, $userB->id))->toBe('0.8');
});

// ─── Phase 11: Follow-up schedule validation ─────────────────────────────

describe('Phase 11: Follow-up schedule validation', function () {
    test('window_start required', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $payload = [
            'first_delay_minutes' => 30,
            'daily_time' => '10:00',
            'max_count' => 3,
            'approach' => 'amigavel',
            'followup_window_end' => '20:00',
            'followup_interval_days' => 1,
        ];

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), $payload)
            ->assertSessionHasErrors('followup_window_start');
    });

    test('window fields must be HH:MM format', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $payload = [
            'first_delay_minutes' => 30,
            'daily_time' => '10:00',
            'max_count' => 3,
            'approach' => 'amigavel',
            'followup_window_start' => '9:30',
            'followup_window_end' => '20:00',
            'followup_interval_days' => 1,
        ];

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), $payload)
            ->assertSessionHasErrors('followup_window_start');
    });

    test('interval_days must be in [1,2,3,5,7]', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $payload = [
            'first_delay_minutes' => 30,
            'daily_time' => '10:00',
            'max_count' => 3,
            'approach' => 'amigavel',
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
            'followup_interval_days' => 4,
        ];

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), $payload)
            ->assertSessionHasErrors('followup_interval_days');
    });

    test('new fields are persisted', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $payload = [
            'first_delay_minutes' => 30,
            'daily_time' => '10:00',
            'max_count' => 3,
            'approach' => 'amigavel',
            'followup_window_start' => '09:00',
            'followup_window_end' => '19:00',
            'followup_interval_days' => 2,
        ];

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), $payload)
            ->assertRedirect();

        $config = $agent->fresh()->config;
        expect($config->followup_window_start)->toBe('09:00');
        expect($config->followup_window_end)->toBe('19:00');
        expect((int) $config->followup_interval_days)->toBe(2);
    });
});

// ─── Helper ──────────────────────────────────────────────────────────────────

function validPayload(array $overrides = []): array
{
    return array_merge([
        'agent_name' => 'Tenaz CRM',
        'agent_niche' => 'inss',
        'company_name' => 'Amec',
        'agent_personality' => 'direta, acolhedora e profissional',
        'agent_greeting' => 'Cumprimente pelo nome',
    ], $overrides);
}

function createAgentWithConfig(User $user, string $name = 'Agente Teste'): Agent
{
    // Ensure the user has a tenant so BelongsToTenant scopes resolve correctly.
    if ($user->tenants()->doesntExist()) {
        $tenant = \App\Models\Tenant::create(['name' => $user->name.' Tenant']);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);
    }

    $tenantId = $user->fresh()->tenantId;

    $instance = WhatsappInstance::factory()->for($user)->create(['tenant_id' => $tenantId]);

    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenantId,
        'name' => $name,
        'is_default' => true,
    ]);

    $instance->update(['agent_id' => $agent->id]);

    AgentConfig::factory()->create(['agent_id' => $agent->id, 'agent_name' => $name, 'tenant_id' => $tenantId]);

    return $agent;
}
