<?php

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\AppSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    AppSetting::flushCache();
});

// ─── SC2: LLM fields cannot be written via direct POST ───────────────────────

test('direct POST with agent_model does not change stored agent_model', function () {
    $user = User::factory()->create();
    $agent = hardeningAgentWithConfig($user);

    // Seed agent_model directly (column is still writable at DB level)
    DB::table('agent_configs')
        ->where('agent_id', $agent->id)
        ->update(['agent_model' => 'gpt-4o-mini']);

    $this->actingAs($user)
        ->post(route('agentes.config.update', $agent), [
            'agent_niche' => 'inss',
            'agent_name' => 'Hardened Bot',
            'company_name' => 'HardenCorp',
            'agent_personality' => 'séria e profissional',
            'agent_greeting' => 'Olá, seja bem-vindo!',
            'agent_model' => 'hacked-model', // must be silently ignored
        ])
        ->assertRedirect();

    $config = AgentConfig::where('agent_id', $agent->id)->first();
    // agent_model must be unchanged
    expect($config->agent_model)->toBe('gpt-4o-mini');
    // persona fields must have been updated
    expect($config->agent_name)->toBe('Hardened Bot');
    expect($config->company_name)->toBe('HardenCorp');
    expect($config->agent_personality)->toBe('séria e profissional');
    expect($config->agent_greeting)->toBe('Olá, seja bem-vindo!');
});

// ─── Escalation drop guard ────────────────────────────────────────────────────

test('direct POST with escalation_whatsapp_number does not write it', function () {
    $user = User::factory()->create();
    $agent = hardeningAgentWithConfig($user);

    // Seed the escalation column directly at DB level (not via fillable)
    DB::table('agent_configs')
        ->where('agent_id', $agent->id)
        ->update(['escalation_whatsapp_number' => '5500000000000']);

    $this->actingAs($user)
        ->post(route('agentes.config.update', $agent), [
            'agent_niche' => 'inss',
            'agent_name' => 'Tenaz CRM',
            'company_name' => 'Amec',
            'agent_personality' => 'direta e profissional',
            'agent_greeting' => 'Olá!',
            'escalation_whatsapp_number' => '5511999999999', // must be silently ignored
        ])
        ->assertRedirect();

    $stored = DB::table('agent_configs')
        ->where('agent_id', $agent->id)
        ->value('escalation_whatsapp_number');

    expect($stored)->toBe('5500000000000');
});

// ─── SC4: existing populated escalation column is still readable ──────────────

test('existing row with populated escalation column is still readable', function () {
    $user = User::factory()->create();
    $agent = hardeningAgentWithConfig($user);

    $configId = AgentConfig::where('agent_id', $agent->id)->value('id');

    // Write directly at DB level — escalation_whatsapp_number is NOT in $fillable
    DB::table('agent_configs')
        ->where('id', $configId)
        ->update(['escalation_whatsapp_number' => '5521988887777']);

    $found = AgentConfig::find($configId);
    expect($found->escalation_whatsapp_number)->toBe('5521988887777');
});

// ─── Persona-only save works ──────────────────────────────────────────────────

test('persona-only save persists and ignores absent LLM fields', function () {
    $user = User::factory()->create();
    $agent = hardeningAgentWithConfig($user);

    $this->actingAs($user)
        ->post(route('agentes.config.update', $agent), [
            'agent_niche' => 'inss',
            'agent_name' => 'NovoNome',
            'company_name' => 'NovaEmpresa',
            'agent_personality' => 'acolhedora',
            'agent_greeting' => 'Bem-vindo à NovaEmpresa!',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $config = AgentConfig::where('agent_id', $agent->id)->first();
    expect($config->agent_name)->toBe('NovoNome');
    expect($config->company_name)->toBe('NovaEmpresa');
    expect($config->agent_personality)->toBe('acolhedora');
    expect($config->agent_greeting)->toBe('Bem-vindo à NovaEmpresa!');
});

// ─── Helper ──────────────────────────────────────────────────────────────────

function hardeningAgentWithConfig(User $user, string $name = 'Agente Hardening'): Agent
{
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

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'agent_name' => $name,
        'tenant_id' => $tenantId,
    ]);

    return $agent;
}
