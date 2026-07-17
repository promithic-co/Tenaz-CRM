<?php

use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Services\ContactCollectedInformationService;
use Illuminate\Validation\ValidationException;

test('manual information is normalized without replacing other contact metadata', function (): void {
    $contact = Contact::factory()->create([
        'extra_data' => ['campaign_code' => 'summer-26'],
    ]);

    app(ContactCollectedInformationService::class)->applyManual($contact, [
        'operation' => 'upsert',
        'label' => 'Profissão',
        'value' => 'Motorista',
    ]);

    expect($contact->fresh()->extra_data)
        ->campaign_code->toBe('summer-26')
        ->collected_information->toHaveKey('profissao')
        ->collected_information->profissao->toBe([
            'label' => 'Profissão',
            'value' => 'Motorista',
            'source' => 'manual',
        ]);
});

test('manual information accepts reasonable general service context', function (): void {
    $contact = Contact::factory()->create();

    app(ContactCollectedInformationService::class)->applyManual($contact, [
        'operation' => 'upsert',
        'label' => 'Histórico',
        'value' => 'Cliente tem contrato há dois anos',
    ]);

    expect(app(ContactCollectedInformationService::class)->items($contact->fresh()))
        ->toContain([
            'key' => 'historico',
            'label' => 'Histórico',
            'value' => 'Cliente tem contrato há dois anos',
            'source' => 'manual',
        ]);
});

test('manual information accepts useful operational facts without guessing from words or digit count', function (string $label, string $value): void {
    $contact = Contact::factory()->create();

    app(ContactCollectedInformationService::class)->applyManual($contact, [
        'operation' => 'upsert',
        'label' => $label,
        'value' => $value,
    ]);

    expect(app(ContactCollectedInformationService::class)->items($contact->fresh()))
        ->toContain([
            'key' => str($label)->slug()->toString(),
            'label' => $label,
            'value' => $value,
            'source' => 'manual',
        ]);
})->with([
    'contract duration' => ['Histórico', 'Contrato ativo há 2 anos'],
    'warranty duration' => ['Garantia', 'Garantia de 3 anos'],
    'protocol' => ['Protocolo', '12345678'],
    'order reference' => ['Referência do pedido', '12345678901234'],
    'transport card' => ['Cartão de transporte', 'Bilhete mensal'],
    'travel agency' => ['Contexto', 'A agência de viagens conta com guia local'],
    'fixed income product' => ['Interesse', 'Renda fixa'],
]);

test('manual information takes precedence over automatic information', function (): void {
    $contact = Contact::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $contact->tenant_id,
        'contact_id' => $contact->id,
        'whatsapp' => $contact->phone,
    ]);
    $service = app(ContactCollectedInformationService::class);

    $service->applyManual($contact, [
        'operation' => 'upsert',
        'label' => 'Profissão',
        'value' => 'Motorista',
    ]);
    $result = $service->applyAi($lead, [
        ['label' => 'Profissão', 'value' => 'Professor'],
        ['label' => 'Objetivo', 'value' => 'Refinanciamento'],
    ]);

    $items = $service->items($contact->fresh());

    expect($result)->toBe(['saved' => 1, 'skipped' => 1])
        ->and($items)->toContain([
            'key' => 'profissao',
            'label' => 'Profissão',
            'value' => 'Motorista',
            'source' => 'manual',
        ])
        ->and($items)->toContain([
            'key' => 'objetivo',
            'label' => 'Objetivo',
            'value' => 'Refinanciamento',
            'source' => 'ai',
        ]);
});

test('conversation endpoint creates the canonical contact and updates collected information', function (): void {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'contact_id' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('conversas.collected-information.update', $lead), [
            'operation' => 'upsert',
            'label' => 'Melhor horário',
            'value' => 'Após 18h',
        ])
        ->assertRedirect();

    $contact = $lead->fresh()->contact;

    expect($contact)->not->toBeNull()
        ->and(app(ContactCollectedInformationService::class)->items($contact))->toContain([
            'key' => 'melhor-horario',
            'label' => 'Melhor horário',
            'value' => 'Após 18h',
            'source' => 'manual',
        ]);
});

test('contact endpoint can remove collected information without removing other metadata', function (): void {
    $user = User::factory()->create();
    $contact = Contact::factory()->forTenant((string) $user->tenantId)->create([
        'extra_data' => [
            'campaign_code' => 'summer-26',
            'collected_information' => [
                'objetivo' => [
                    'label' => 'Objetivo',
                    'value' => 'Refinanciamento',
                    'source' => 'manual',
                ],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->patch(route('contatos.collected-information.update', $contact), [
            'operation' => 'delete',
            'key' => 'objetivo',
        ])
        ->assertRedirect();

    expect($contact->fresh()->extra_data)
        ->campaign_code->toBe('summer-26')
        ->collected_information->toBe([]);
});

test('collected information validates manual input limits', function (): void {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['tenant_id' => $user->tenantId]);

    $this->actingAs($user)
        ->patch(route('conversas.collected-information.update', $lead), [
            'operation' => 'upsert',
            'label' => str_repeat('a', 61),
            'value' => '',
        ])
        ->assertSessionHasErrors(['label', 'value']);
});

test('manual information rejects labels that cannot produce a stable key', function (): void {
    $contact = Contact::factory()->create();

    expect(fn () => app(ContactCollectedInformationService::class)->applyManual($contact, [
        'operation' => 'upsert',
        'label' => '!!!',
        'value' => 'Informação válida',
    ]))->toThrow(ValidationException::class);

    expect(app(ContactCollectedInformationService::class)->items($contact->fresh()))->toBe([]);
});

test('manual information rejects normalized key collisions', function (): void {
    $contact = Contact::factory()->create();
    $service = app(ContactCollectedInformationService::class);

    $service->applyManual($contact, [
        'operation' => 'upsert',
        'label' => 'Profissão',
        'value' => 'Motorista',
    ]);

    expect(fn () => $service->applyManual($contact, [
        'operation' => 'upsert',
        'label' => 'Profissao',
        'value' => 'Professor',
    ]))->toThrow(ValidationException::class);

    expect($service->items($contact->fresh()))->toBe([[
        'key' => 'profissao',
        'label' => 'Profissão',
        'value' => 'Motorista',
        'source' => 'manual',
    ]]);
});

test('conversation endpoint never writes to a contact from another tenant', function (): void {
    $user = User::factory()->create();
    $foreignUser = User::factory()->create();
    $foreignContact = Contact::factory()->forTenant((string) $foreignUser->tenantId)->create([
        'extra_data' => ['foreign' => true],
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => (string) $user->tenantId,
        'contact_id' => $foreignContact->id,
        'whatsapp' => '5565999999999',
    ]);

    $this->actingAs($user)
        ->patch(route('conversas.collected-information.update', $lead), [
            'operation' => 'upsert',
            'label' => 'Objetivo',
            'value' => 'Refinanciamento',
        ])
        ->assertRedirect();

    $lead->refresh();
    $canonicalContact = Contact::withoutGlobalScopes()->findOrFail($lead->contact_id);

    expect($foreignContact->fresh()->extra_data)->toBe(['foreign' => true])
        ->and((string) $canonicalContact->tenant_id)->toBe((string) $user->tenantId)
        ->and($canonicalContact->id)->not->toBe($foreignContact->id)
        ->and(app(ContactCollectedInformationService::class)->items($canonicalContact))
        ->toContain([
            'key' => 'objetivo',
            'label' => 'Objetivo',
            'value' => 'Refinanciamento',
            'source' => 'manual',
        ]);
});

test('manual information accepts personal data relevant to the service', function (string $label, string $value): void {
    $contact = Contact::factory()->create();

    app(ContactCollectedInformationService::class)->applyManual($contact, [
        'operation' => 'upsert',
        'label' => $label,
        'value' => $value,
    ]);

    expect(app(ContactCollectedInformationService::class)->items($contact->fresh()))
        ->toContain([
            'key' => str($label)->slug()->toString(),
            'label' => $label,
            'value' => $value,
            'source' => 'manual',
        ]);
})->with([
    'cpf' => ['CPF', '123.456.789-00'],
    'birth date' => ['Data de nascimento', '15/03/1984'],
    'idade' => ['Idade', 'Tem 42 anos'],
    'email' => ['E-mail de retorno', 'cliente@example.com'],
    'telefone' => ['Telefone alternativo', '(11) 98765-4321'],
    'renda' => ['Renda mensal', 'R$ 5.000,00'],
]);

test('manual information rejects values above the length limits', function (): void {
    $contact = Contact::factory()->create();

    expect(fn () => app(ContactCollectedInformationService::class)->applyManual($contact, [
        'operation' => 'upsert',
        'label' => 'Contexto',
        'value' => str_repeat('a', 501),
    ]))->toThrow(ValidationException::class);

    expect(app(ContactCollectedInformationService::class)->items($contact->fresh()))->toBe([]);
});
