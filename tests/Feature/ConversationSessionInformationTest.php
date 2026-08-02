<?php

use App\Ai\Agents\GenericAgent;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Contact;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\NicheTemplate;
use App\Models\User;
use App\Services\ConversationSessionInformationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Lead, 2: ConversationSession}
 */
function sessionInfoSetup(): array
{
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()->forLead($lead)->open()->create(['number' => 1]);

    return [$user, $lead, $session];
}

function sessionInfoPromptLead(array $leadState = []): Lead
{
    NicheTemplate::factory()->create([
        'slug' => 'clinica-recepcao-info',
        'niche_sections' => [
            ['title' => 'ESCOPO DO ATENDIMENTO', 'content' => 'Acolher o paciente.'],
        ],
    ]);

    $agent = Agent::factory()->has(
        AgentConfig::factory()->state([
            'agent_niche' => 'generic',
            'template_slug' => 'clinica-recepcao-info',
            'agent_name' => 'Sofia',
            'company_name' => 'Clínica Vida',
        ]),
        'config'
    )->create();

    return Lead::factory()->create(array_merge([
        'agent_id' => $agent->id,
        'modo' => 'receptivo',
        'status' => 'novo',
        'nome' => 'Lucas',
    ], $leadState));
}

test('upsert stores a free-form entry on the open atendimento', function () {
    [$user, $lead, $session] = sessionInfoSetup();

    $this->actingAs($user)
        ->patch(route('conversas.sessions.information.update', [$lead, $session]), [
            'operation' => 'upsert',
            'label' => 'Melhor horário',
            'value' => 'Cliente prefere contato à tarde',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(ConversationSessionInformationService::class)->items($session->fresh()))
        ->toHaveCount(1)
        ->toContain([
            'key' => 'melhor-horario',
            'label' => 'Melhor horário',
            'value' => 'Cliente prefere contato à tarde',
            'source' => 'manual',
        ]);
});

test('upsert with a key edits the existing entry, renaming its label', function () {
    [$user, $lead, $session] = sessionInfoSetup();
    $session->update(['collected_information' => [
        'melhor-horario' => ['label' => 'Melhor horário', 'value' => 'Tarde', 'source' => 'manual'],
    ]]);

    $this->actingAs($user)
        ->patch(route('conversas.sessions.information.update', [$lead, $session]), [
            'operation' => 'upsert',
            'key' => 'melhor-horario',
            'label' => 'Horário de contato',
            'value' => 'Somente após as 18h',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $items = app(ConversationSessionInformationService::class)->items($session->fresh());

    expect($items)->toHaveCount(1)
        ->and($items[0]['key'])->toBe('horario-de-contato')
        ->and($items[0]['value'])->toBe('Somente após as 18h');
});

test('delete removes the entry', function () {
    [$user, $lead, $session] = sessionInfoSetup();
    $session->update(['collected_information' => [
        'pendencia' => ['label' => 'Pendência', 'value' => 'Enviar documentos', 'source' => 'manual'],
    ]]);

    $this->actingAs($user)
        ->patch(route('conversas.sessions.information.update', [$lead, $session]), [
            'operation' => 'delete',
            'key' => 'pendencia',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(ConversationSessionInformationService::class)->items($session->fresh()))->toBeEmpty();
});

test('upsert rejects a duplicate label', function () {
    [$user, $lead, $session] = sessionInfoSetup();
    $session->update(['collected_information' => [
        'pendencia' => ['label' => 'Pendência', 'value' => 'Enviar documentos', 'source' => 'manual'],
    ]]);

    $this->actingAs($user)
        ->patch(route('conversas.sessions.information.update', [$lead, $session]), [
            'operation' => 'upsert',
            'label' => 'Pendência',
            'value' => 'Outra coisa',
        ])
        ->assertSessionHasErrors('label');

    expect(app(ConversationSessionInformationService::class)->items($session->fresh()))
        ->toHaveCount(1);
});

test('update refuses a closed atendimento', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()
        ->forLead($lead)
        ->closed(ConversationSession::OUTCOME_CONVERTED)
        ->create(['number' => 1]);

    $this->actingAs($user)
        ->patch(route('conversas.sessions.information.update', [$lead, $session]), [
            'operation' => 'upsert',
            'label' => 'Tarde demais',
            'value' => 'Não deve entrar',
        ])
        ->assertSessionHasErrors('value');

    expect($session->fresh()->collected_information)->toBeNull();
});

test('update refuses a session that belongs to another lead', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $otherLead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()->forLead($otherLead)->open()->create();

    $this->actingAs($user)
        ->patch(route('conversas.sessions.information.update', [$lead, $session]), [
            'operation' => 'upsert',
            'label' => 'Cruzado',
            'value' => 'Não deve entrar',
        ])
        ->assertNotFound();
});

test('update is forbidden for a lead in another tenant', function () {
    [, $lead, $session] = sessionInfoSetup();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('conversas.sessions.information.update', [$lead, $session]), [
            'operation' => 'upsert',
            'label' => 'Invasor',
            'value' => 'Não deve entrar',
        ])
        ->assertNotFound();

    expect($session->fresh()->collected_information)->toBeNull();
});

test('panel carries the session entries on the payload', function () {
    [$user, $lead, $session] = sessionInfoSetup();
    $session->update(['collected_information' => [
        'melhor-horario' => ['label' => 'Melhor horário', 'value' => 'Tarde', 'source' => 'manual'],
    ]]);

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.sessions.0.collected_information.0.label', 'Melhor horário')
            ->where('activeConversation.sessions.0.collected_information.0.value', 'Tarde')
        );
});

test('agent prompt carries the open atendimento entries', function () {
    $lead = sessionInfoPromptLead();
    ConversationSession::factory()->forLead($lead)->open()->create([
        'number' => 1,
        'collected_information' => [
            'melhor-horario' => ['label' => 'Melhor horário', 'value' => 'Somente à tarde', 'source' => 'manual'],
        ],
    ]);

    $instructions = (string) (new GenericAgent($lead))->instructions();

    expect($instructions)->toContain('Informações do atendimento atual:')
        ->and($instructions)->toContain('- Melhor horário: Somente à tarde');
});

test('agent prompt ignores entries from a closed atendimento', function () {
    $lead = sessionInfoPromptLead();
    ConversationSession::factory()->forLead($lead)->closed()->create([
        'number' => 1,
        'collected_information' => [
            'arquivado' => ['label' => 'Arquivado', 'value' => 'Ciclo antigo', 'source' => 'manual'],
        ],
    ]);

    $instructions = (string) (new GenericAgent($lead))->instructions();

    expect($instructions)->not->toContain('Informações do atendimento atual:')
        ->and($instructions)->not->toContain('Ciclo antigo');
});

test('agent prompt carries the contact collected information', function () {
    $contact = Contact::factory()->create([
        'extra_data' => [
            'collected_information' => [
                'assunto' => ['label' => 'Assunto', 'value' => 'Renovação do contrato', 'source' => 'ai'],
            ],
        ],
    ]);

    $lead = sessionInfoPromptLead([
        'tenant_id' => $contact->tenant_id,
        'contact_id' => $contact->id,
        'whatsapp' => $contact->phone,
    ]);

    $instructions = (string) (new GenericAgent($lead))->instructions();

    expect($instructions)->toContain('Informações do contato:')
        ->and($instructions)->toContain('- Assunto: Renovação do contrato');
});
