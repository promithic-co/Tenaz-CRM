<?php

use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Services\WhatsApp\TemplateParameterResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function resolver(): TemplateParameterResolver
{
    return app(TemplateParameterResolver::class);
}

/**
 * A lead under a real tenant, optionally linked to a canonical contact.
 *
 * @param  array<string, mixed>|null  $contactAttributes  null leaves the lead without a contact
 * @param  array<string, mixed>  $leadAttributes
 */
function leadFor(?array $contactAttributes = [], array $leadAttributes = []): Lead
{
    $tenantId = (string) User::factory()->create()->tenantId;

    if ($contactAttributes !== null) {
        $contact = Contact::factory()->create(array_merge(['tenant_id' => $tenantId], $contactAttributes));
        $leadAttributes['contact_id'] = $contact->id;
    }

    return Lead::factory()->forTenant($tenantId)->create($leadAttributes);
}

/**
 * @param  list<string>  $examples
 * @return array<int, mixed>
 */
function bodyComponents(string $text, array $examples): array
{
    return [[
        'type' => 'BODY',
        'text' => $text,
        'example' => ['body_text' => [$examples]],
    ]];
}

it('fills the first body variable with the contact name', function () {
    $lead = leadFor(['name' => 'Marcos Vinicius'], ['nome' => 'Lead Desatualizado']);

    $resolved = resolver()->resolve($lead, bodyComponents('Olá {{1}}, tudo bem?', ['Maria']));

    expect($resolved['parameters'])->toBe(['body' => ['1' => 'Marcos Vinicius']])
        ->and($resolved['unresolved'])->toBe([]);
});

it('falls back to the lead name when no contact is linked', function () {
    $lead = leadFor(null, ['nome' => 'Joana Sem Contato']);

    $resolved = resolver()->resolve($lead, bodyComponents('Olá {{1}}!', ['Maria']));

    expect($resolved['parameters']['body']['1'])->toBe('Joana Sem Contato');
});

it('resolves a variable whose example names a canonical contact field', function () {
    $lead = leadFor(['name' => 'Ana Paula', 'cpf' => '12345678901']);

    $resolved = resolver()->resolve(
        $lead,
        bodyComponents('{{1}}, confirme o CPF {{2}}.', ['Maria', 'cpf']),
    );

    expect($resolved['parameters']['body'])->toBe(['1' => 'Ana Paula', '2' => '12345678901']);
});

it('resolves a variable whose example names an extra_data key, ignoring accents and case', function () {
    $lead = leadFor(['name' => 'Ana', 'extra_data' => ['valor_liberado' => 'R$ 4.200,00']]);

    $resolved = resolver()->resolve(
        $lead,
        bodyComponents('{{1}}, seu limite é {{2}}.', ['Maria', 'Valor Liberado']),
    );

    expect($resolved['parameters']['body']['2'])->toBe('R$ 4.200,00');
});

it('never uses the Meta example as a value, leaving the field for the operator', function () {
    $lead = leadFor();

    $resolved = resolver()->resolve(
        $lead,
        bodyComponents('{{1}}, sua proposta {{2}} saiu.', ['Maria', '#0042']),
    );

    expect($resolved['parameters']['body'])->not->toHaveKey('2')
        ->and($resolved['unresolved'])->toBe(['body.2']);
});

it('leaves media headers to the operator', function () {
    $lead = leadFor();

    $resolved = resolver()->resolve($lead, [
        ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['https://x/y.jpg']]],
        ['type' => 'BODY', 'text' => 'Olá {{1}}.', 'example' => ['body_text' => [['Maria']]]],
    ]);

    expect($resolved['unresolved'])->toBe(['header.media'])
        ->and($resolved['parameters']['body']['1'])->not->toBeEmpty();
});

it('resolves nothing for a template with no variables', function () {
    $resolved = resolver()->resolve(leadFor(), [['type' => 'BODY', 'text' => 'Mensagem fixa.']]);

    expect($resolved)->toBe(['parameters' => [], 'unresolved' => []]);
});

it('returns empty for an unsupported template instead of throwing', function () {
    $resolved = resolver()->resolve(leadFor(), [['type' => 'BODY', 'text' => 'Olá {{nome}}.']]);

    expect($resolved)->toBe(['parameters' => [], 'unresolved' => []]);
});
