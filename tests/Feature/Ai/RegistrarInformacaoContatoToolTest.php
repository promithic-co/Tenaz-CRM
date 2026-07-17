<?php

use App\Ai\Agents\CredFlowAgent;
use App\Ai\Tools\RegistrarInformacaoContatoTool;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\ContactCollectedInformationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Serializer;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('tool schema asks only for a dynamic label and value', function (): void {
    $lead = Lead::factory()->create();
    $schema = (new RegistrarInformacaoContatoTool($lead))->schema(new JsonSchemaTypeFactory);
    $information = Serializer::serialize($schema['informacoes']);

    expect($information['items']['properties'])
        ->toHaveKeys(['label', 'value'])
        ->not->toHaveKey('category');
});

test('tool stores reasonable general service information', function (): void {
    $contact = Contact::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $contact->tenant_id,
        'contact_id' => $contact->id,
        'whatsapp' => $contact->phone,
    ]);

    $tool = new RegistrarInformacaoContatoTool($lead);
    $result = (string) $tool->handle(new Request([
        'informacoes' => [
            ['label' => 'Assunto', 'value' => 'Renovação do contrato'],
            ['label' => 'Histórico', 'value' => 'Cliente tem contrato há dois anos'],
            ['label' => 'Pendência', 'value' => 'Enviar proposta revisada'],
            ['label' => 'Próximo passo', 'value' => 'Retornar na sexta-feira'],
        ],
    ]));

    expect(json_decode($result, true))
        ->status->toBe('success')
        ->data->saved->toBe(4)
        ->data->skipped->toBe(0)
        ->and(app(ContactCollectedInformationService::class)->items($contact->fresh()))
        ->toHaveCount(4)
        ->toContain([
            'key' => 'pendencia',
            'label' => 'Pendência',
            'value' => 'Enviar proposta revisada',
            'source' => 'ai',
        ])
        ->and((string) $tool->description())
        ->toContain('rótulo livre')
        ->toContain('credenciais');
});

test('tool does not overwrite information registered manually', function (): void {
    $contact = Contact::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $contact->tenant_id,
        'contact_id' => $contact->id,
        'whatsapp' => $contact->phone,
    ]);
    $service = app(ContactCollectedInformationService::class);

    $service->applyManual($contact, [
        'operation' => 'upsert',
        'label' => 'Pendência',
        'value' => 'Aguardar documentos',
    ]);

    $result = (string) (new RegistrarInformacaoContatoTool($lead))->handle(new Request([
        'informacoes' => [
            ['label' => 'Pendência', 'value' => 'Nenhuma'],
            ['label' => 'Assunto', 'value' => 'Renovação'],
        ],
    ]));

    expect(json_decode($result, true))
        ->status->toBe('success')
        ->data->saved->toBe(1)
        ->data->skipped->toBe(1)
        ->and($service->items($contact->fresh()))
        ->toContain([
            'key' => 'pendencia',
            'label' => 'Pendência',
            'value' => 'Aguardar documentos',
            'source' => 'manual',
        ]);
});

test('tool stores personal data relevant to the service', function (): void {
    $contact = Contact::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $contact->tenant_id,
        'contact_id' => $contact->id,
        'whatsapp' => $contact->phone,
    ]);

    $result = (string) (new RegistrarInformacaoContatoTool($lead))->handle(new Request([
        'informacoes' => [
            ['label' => 'CPF', 'value' => '123.456.789-00'],
            ['label' => 'Data de nascimento', 'value' => '15/03/1984'],
            ['label' => 'Telefone alternativo', 'value' => '(11) 98765-4321'],
            ['label' => 'Renda mensal', 'value' => 'R$ 5.000,00'],
            ['label' => 'Interesse', 'value' => 'Plano empresarial'],
        ],
    ]));

    expect(json_decode($result, true))
        ->status->toBe('success')
        ->data->saved->toBe(5)
        ->data->skipped->toBe(0)
        ->and(app(ContactCollectedInformationService::class)->items($contact->fresh()))
        ->toHaveCount(5)
        ->toContain([
            'key' => 'cpf',
            'label' => 'CPF',
            'value' => '123.456.789-00',
            'source' => 'ai',
        ]);
});

test('tool skips items with non-string label or value', function (): void {
    $contact = Contact::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $contact->tenant_id,
        'contact_id' => $contact->id,
        'whatsapp' => $contact->phone,
    ]);

    $result = (string) (new RegistrarInformacaoContatoTool($lead))->handle(new Request([
        'informacoes' => [
            ['label' => ['inválido'], 'value' => 'Teste'],
            ['label' => 'Pendência', 'value' => 42],
            ['label' => 'Assunto', 'value' => 'Renovação'],
        ],
    ]));

    expect(json_decode($result, true))
        ->status->toBe('success')
        ->data->saved->toBe(1)
        ->data->skipped->toBe(2)
        ->and(app(ContactCollectedInformationService::class)->items($contact->fresh()))
        ->toBe([[
            'key' => 'assunto',
            'label' => 'Assunto',
            'value' => 'Renovação',
            'source' => 'ai',
        ]]);
});

test('tool preserves ordinary service phrases', function (): void {
    $contact = Contact::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $contact->tenant_id,
        'contact_id' => $contact->id,
        'whatsapp' => $contact->phone,
    ]);

    $result = (string) (new RegistrarInformacaoContatoTool($lead))->handle(new Request([
        'informacoes' => [
            ['label' => 'Histórico', 'value' => 'Contrato ativo há 2 anos'],
            ['label' => 'Garantia', 'value' => 'Garantia de 3 anos'],
            ['label' => 'Protocolo', 'value' => '12345678'],
            ['label' => 'Referência do pedido', 'value' => '12345678901234'],
            ['label' => 'Cartão de transporte', 'value' => 'Bilhete mensal'],
            ['label' => 'Contexto', 'value' => 'A agência de viagens conta com guia local'],
            ['label' => 'Interesse', 'value' => 'Renda fixa'],
            ['label' => 'Preferência', 'value' => 'Prefere retorno pelo WhatsApp'],
            ['label' => 'Disponibilidade', 'value' => 'Possui banco de horas'],
            ['label' => 'Benefício', 'value' => 'Usa cartão de transporte'],
        ],
    ]));

    expect(json_decode($result, true))
        ->status->toBe('success')
        ->data->saved->toBe(10)
        ->data->skipped->toBe(0)
        ->and(app(ContactCollectedInformationService::class)->items($contact->fresh()))
        ->toHaveCount(10);
});

test('tool blocks malformed information payload without creating a contact', function (mixed $payload): void {
    $lead = Lead::factory()->create(['contact_id' => null]);

    $result = (string) (new RegistrarInformacaoContatoTool($lead))->handle(new Request([
        'informacoes' => $payload,
    ]));

    expect(json_decode($result, true))
        ->status->toBe('blocked')
        ->and($lead->fresh()->contact_id)->toBeNull();
})->with([
    'string payload' => ['invalid'],
    'list of scalars' => [fn (): array => ['texto solto']],
    'items without string label and value' => [fn (): array => [
        ['label' => ['inválido'], 'value' => 'Teste'],
        ['label' => 'Pendência', 'value' => 42],
    ]],
    'items with empty strings' => [fn (): array => [
        ['label' => '   ', 'value' => 'Teste'],
        ['label' => 'Assunto', 'value' => ''],
    ]],
]);

test('standard customer service agents expose the contact information tool', function (): void {
    $lead = Lead::factory()->create(['status' => 'novo']);

    $tools = collect((new CredFlowAgent($lead))->tools());

    expect($tools->contains(
        fn (object $tool): bool => $tool instanceof RegistrarInformacaoContatoTool
    ))->toBeTrue();
});

test('lead system context does not interpolate registered information', function (): void {
    $contact = Contact::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $contact->tenant_id,
        'contact_id' => $contact->id,
        'whatsapp' => $contact->phone,
        'nome' => 'Lucas',
    ]);

    app(ContactCollectedInformationService::class)->applyManual($contact, [
        'operation' => 'upsert',
        'label' => 'Assunto',
        'value' => 'Renovação',
    ]);

    $agent = new class($lead) extends CredFlowAgent
    {
        public function exposedLeadContext(): string
        {
            return $this->buildLeadContext();
        }
    };

    expect($agent->exposedLeadContext())
        ->not->toContain('Informações do atendimento')
        ->not->toContain('Assunto: Renovação');
});
