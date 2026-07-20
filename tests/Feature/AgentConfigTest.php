<?php

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\AgentFollowUpSetting;
use App\Models\AppSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\AgentConfigResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
            ->has('specializations', 4)
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
            'enabled' => true,
            'first_delay_minutes' => 45,
            'max_count' => 5,
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
            'min_interval_minutes' => 120,
            'message_type' => 'urgencia',
            'tone' => 'direto',
            'persuasion_intensity' => 4,
            'custom_instructions' => 'Mencionar que a simulacao ja esta pronta.',
        ])
        ->assertRedirect();

    $rowA = AgentFollowUpSetting::withoutGlobalScope('tenant')->where('agent_id', $agentA->id)->first();
    expect($rowA->enabled)->toBeTrue();
    expect($rowA->first_delay_minutes)->toBe(45);
    expect($rowA->min_interval_minutes)->toBe(120);
    expect($rowA->max_attempts_within_window)->toBe(5);
    expect($rowA->tone)->toBe('direto');

    // Legacy AgentConfig mirror keeps primary fields only — dead UI columns untouched.
    expect($agentA->fresh()->config->followup_first_delay_minutes)->toBe(45);
    expect($agentA->fresh()->config->followup_max_count)->toBe(5);
    expect($agentA->fresh()->config->followup_message_type)->toBe('urgencia');
    expect($agentA->fresh()->config->followup_tone)->toBe('direto');
    expect($agentA->fresh()->config->followup_persuasion_intensity)->toBe(4);
    expect($agentA->fresh()->config->followup_custom_instructions)->toBe('Mencionar que a simulacao ja esta pronta.');

    expect(AgentFollowUpSetting::withoutGlobalScope('tenant')->where('agent_id', $agentB->id)->exists())->toBeFalse();
});

test('follow-up enabled=false is persisted', function () {
    $user = User::factory()->create();
    $agent = createAgentWithConfig($user);

    $this->actingAs($user)
        ->post(route('agentes.followup.update', $agent), [
            'enabled' => false,
            'first_delay_minutes' => 10,
            'max_count' => 2,
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
            'min_interval_minutes' => 60,
        ])
        ->assertRedirect();

    $row = AgentFollowUpSetting::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->first();
    expect($row->enabled)->toBeFalse();
});

test('min_interval_minutes outside the preset set is rejected', function () {
    $user = User::factory()->create();
    $agent = createAgentWithConfig($user);

    $this->actingAs($user)
        ->post(route('agentes.followup.update', $agent), [
            'enabled' => true,
            'first_delay_minutes' => 10,
            'max_count' => 2,
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
            'min_interval_minutes' => 45,
        ])
        ->assertSessionHasErrors('min_interval_minutes');
});

test('follow-up show does not create an agent_configs row', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);

    expect(AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('agentes.followup', $agent))
        ->assertOk();

    expect(AgentConfig::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->exists())->toBeFalse();
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
    function followupPayload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'first_delay_minutes' => 30,
            'max_count' => 3,
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
            'min_interval_minutes' => 60,
        ], $overrides);
    }

    test('window_start required', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $payload = followupPayload();
        unset($payload['followup_window_start']);

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), $payload)
            ->assertSessionHasErrors('followup_window_start');
    });

    test('window fields must be HH:MM format', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), followupPayload(['followup_window_start' => '9:30']))
            ->assertSessionHasErrors('followup_window_start');
    });

    test('min_interval_minutes must be one of the presets', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), followupPayload(['min_interval_minutes' => 90]))
            ->assertSessionHasErrors('min_interval_minutes');
    });

    test('window fields reject out-of-range times', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), followupPayload(['followup_window_start' => '25:99']))
            ->assertSessionHasErrors('followup_window_start');
    });

    test('overnight window (end before start) is accepted', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), followupPayload([
                'followup_window_start' => '22:00',
                'followup_window_end' => '06:00',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $row = AgentFollowUpSetting::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->first();
        expect(substr((string) $row->business_window_start, 0, 5))->toBe('22:00');
        expect(substr((string) $row->business_window_end, 0, 5))->toBe('06:00');
    });

    test('new fields are persisted', function () {
        $user = User::factory()->create();
        $agent = createAgentWithConfig($user);

        $this->actingAs($user)
            ->post(route('agentes.followup.update', $agent), followupPayload([
                'followup_window_start' => '09:00',
                'followup_window_end' => '19:00',
                'min_interval_minutes' => 240,
            ]))
            ->assertRedirect();

        $row = AgentFollowUpSetting::withoutGlobalScope('tenant')->where('agent_id', $agent->id)->first();
        expect(substr((string) $row->business_window_start, 0, 5))->toBe('09:00');
        expect(substr((string) $row->business_window_end, 0, 5))->toBe('19:00');
        expect($row->min_interval_minutes)->toBe(240);
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
        $tenant = Tenant::create(['name' => $user->name.' Tenant']);
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
