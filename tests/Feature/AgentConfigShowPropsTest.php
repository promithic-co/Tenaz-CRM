<?php

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\WhatsappInstance;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    AppSetting::flushCache();
});

// ─── SC1: show() sends only persona props ─────────────────────────────────────

test('config page renders only persona props', function () {
    $user = User::factory()->create();
    $agent = createAgentForShowPropsTest($user);

    $this->actingAs($user)
        ->get(route('agentes.config', $agent))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agente-config/Index')
            ->where('agent.id', $agent->id)
            ->has('settings.agent_name')
            ->has('settings.company_name')
            ->has('settings.agent_personality')
            ->has('settings.agent_greeting')
            ->missing('settings.temperature')
            ->missing('settings.agent_model')
            ->missing('settings.escalation_whatsapp_number')
            ->missing('settings.max_tokens')
            ->missing('settings.transcription_provider')
        );
});

// ─── Helper ───────────────────────────────────────────────────────────────────

function createAgentForShowPropsTest(User $user, string $name = 'SC1 Test Agent'): Agent
{
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

    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'agent_name' => $name,
        'tenant_id' => $tenantId,
    ]);

    return $agent;
}
