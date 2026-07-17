<?php

use App\Models\Agent;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('test_index_filters_by_search', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    Lead::factory()->forAgent($agent)->create(['nome' => 'Carlos Andrade']);
    Lead::factory()->forAgent($agent)->create(['nome' => 'Maria Silva']);

    $this->actingAs($user)
        ->get(route('conversas.index', ['search' => 'Carlos']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.total', 1)
            ->where('leads.data.0.nome', 'Carlos Andrade')
        );
});

test('test_index_excludes_non_matching_search', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    Lead::factory()->forAgent($agent)->create(['nome' => 'Carlos Andrade']);
    Lead::factory()->forAgent($agent)->create(['nome' => 'Maria Silva']);

    $this->actingAs($user)
        ->get(route('conversas.index', ['search' => 'Xyzzy']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.total', 0)
        );
});

test('test_index_sorts_by_column', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    Lead::factory()->forAgent($agent)->create(['nome' => 'Zilda Nunes']);
    Lead::factory()->forAgent($agent)->create(['nome' => 'Ana Borges']);

    $this->actingAs($user)
        ->get(route('conversas.index', ['sort' => 'nome', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.data.0.nome', 'Ana Borges')
            ->where('leads.data.1.nome', 'Zilda Nunes')
        );
});

test('test_index_defaults_to_all_instances', function () {
    $user = User::factory()->create();
    $defaultAgent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $otherAgent = Agent::factory()->create(['user_id' => $user->id]);

    Lead::factory()->forAgent($defaultAgent)->create(['nome' => 'Lead Agente Default']);
    Lead::factory()->forAgent($otherAgent)->create(['nome' => 'Lead Outro Agente']);

    $this->actingAs($user)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('filters.instance', '')
            ->where('leads.total', 2)
        );
});

test('test_index_reports_agentless_lead_effective_mode_as_manual', function () {
    $user = User::factory()->create();

    $instance = WhatsappInstance::factory()->for($user)->create([
        'name' => 'crm-only-instance',
        'agent_id' => null,
        'default_ai_mode' => Lead::AI_MODE_AUTOMATIC,
    ]);

    Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => null,
        'whatsapp_instance_id' => $instance->id,
        'nome' => 'Lead CRM',
    ]);

    $this->actingAs($user)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.total', 1)
            ->where('leads.data.0.effective_ai_mode', Lead::AI_MODE_MANUAL)
        );
});

test('test_show_renders_unified_inbox_with_active_conversation', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $conversationId = Str::uuid()->toString();
    $contact = Contact::factory()->forTenant((string) $user->tenantId)->create([
        'extra_data' => [
            'collected_information' => [
                'objetivo' => [
                    'label' => 'Objetivo',
                    'value' => 'Refinanciamento',
                    'source' => 'manual',
                ],
            ],
        ],
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'nome' => 'Ana Paula Ferreira',
        'conversation_id' => $conversationId,
        'followup_status' => 'active',
        'status' => 'escalado',
        'contact_id' => $contact->id,
    ]);

    Lead::factory()->forAgent($agent)->create(['nome' => 'Outro Lead']);

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'Atendimento Ana',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => Str::uuid()->toString(),
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'aria',
        'role' => 'user',
        'content' => 'Oi, recebi a mensagem sobre a proposta.',
        'attachments' => '',
        'tool_calls' => '',
        'tool_results' => '',
        'usage' => '',
        'meta' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.total', 2)
            ->where('activeConversation.lead.id', $lead->id)
            ->where('activeConversation.lead.nome', 'Ana Paula Ferreira')
            ->where('activeConversation.lead.status', 'escalado')
            ->where('activeConversation.lead.collected_information.0.label', 'Objetivo')
            ->where('activeConversation.lead.collected_information.0.value', 'Refinanciamento')
            ->where('activeConversation.mensagens.0.content', 'Oi, recebi a mensagem sobre a proposta.')
            ->where('activeConversation.followupStatus', $lead->followup_status)
        );
});

test('test_index_filters_by_instance', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'instancia-principal',
        'display_name' => 'Principal',
    ]);

    Lead::factory()->forAgent($agent)->create([
        'nome' => 'Lead Principal',
        'whatsapp_instance_id' => $instance->id,
    ]);
    Lead::factory()->forAgent($agent)->create([
        'nome' => 'Lead Secundario',
        'whatsapp_instance_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('conversas.index', ['instance' => 'instancia-principal']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('filters.instance', 'instancia-principal')
            ->where('instances.0.name', 'instancia-principal')
            ->where('leads.total', 1)
            ->where('leads.data.0.nome', 'Lead Principal')
        );
});

test('test_preview_returns_json', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'nome' => 'João Teste',
        'status' => 'qualificado',
    ]);

    $this->actingAs($user)
        ->getJson(route('conversas.preview', $lead))
        ->assertOk()
        ->assertJsonStructure([
            'lead' => [
                'id',
                'nome',
                'whatsapp',
                'cpf',
                'idade',
                'status',
                'followup_count',
                'followup_status',
                'credito_json',
                'ultima_interacao',
            ],
            'messages',
        ])
        ->assertJsonPath('lead.id', $lead->id)
        ->assertJsonPath('lead.status', 'qualificado');
});

test('test_preview_returns_last_5_messages', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $conversationId = Str::uuid()->toString();

    $lead = Lead::factory()->forAgent($agent)->create([
        'conversation_id' => $conversationId,
    ]);

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($i = 1; $i <= 7; $i++) {
        DB::table('agent_conversation_messages')->insert([
            'id' => Str::uuid()->toString(),
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => 'aria',
            'role' => 'user',
            'content' => "Mensagem {$i}",
            'attachments' => '',
            'tool_calls' => '',
            'tool_results' => '',
            'usage' => '',
            'meta' => '',
            'created_at' => now()->addSeconds($i),
            'updated_at' => now()->addSeconds($i),
        ]);
    }

    $response = $this->actingAs($user)
        ->getJson(route('conversas.preview', $lead))
        ->assertOk();

    expect(count($response->json('messages')))->toBe(5);
    // Should be the last 5 messages (3 through 7), in chronological order
    expect($response->json('messages.0.content'))->toBe('Mensagem 3');
    expect($response->json('messages.4.content'))->toBe('Mensagem 7');
});

test('test_preview_forbidden_for_other_tenant', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $agentA = Agent::factory()->create(['user_id' => $userA->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agentA)->create();

    $this->actingAs($userB)
        ->getJson(route('conversas.preview', $lead))
        ->assertNotFound();
});

test('test_index_passes_search_sort_direction_in_filters', function () {
    $user = User::factory()->create();
    Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $this->actingAs($user)
        ->get(route('conversas.index', [
            'search' => 'João',
            'sort' => 'nome',
            'direction' => 'asc',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('filters.search', 'João')
            ->where('filters.sort', 'nome')
            ->where('filters.direction', 'asc')
        );
});
