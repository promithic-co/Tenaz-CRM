<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Tenant, 1: User, 2: Lead}
 */
function panelCustomFieldsSetup(TenantRole $role = TenantRole::Owner): array
{
    $tenant = Tenant::create(['name' => 'PanelCustomFieldsTest']);

    $actor = User::factory()->create();
    $actor->tenants()->detach();
    $actor->tenants()->attach($tenant->id, ['role' => $role->value]);

    $agent = Agent::factory()->create([
        'user_id' => $actor->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    // A restricted user only reaches leads assigned to them (LeadPolicy).
    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'assigned_user_id' => $actor->id,
    ]);

    return [$tenant, $actor, $lead];
}

test('the panel exposes the tenant fields with this lead current value', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    $field = CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'renda',
        'label' => 'Renda',
        'type' => 'number',
        'sort_order' => 0,
    ]);
    CustomFieldValue::create([
        'custom_field_id' => $field->id,
        'entity_type' => 'lead',
        'entity_id' => $lead->id,
        'value_number' => 3500,
    ]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activeConversation.custom_fields', 1)
            ->where('activeConversation.custom_fields.0.slug', 'renda')
            ->where('activeConversation.custom_fields.0.type', 'number')
            ->where('activeConversation.custom_fields.0.editable', true)
            // JSON gives back 3500 or 3500.0 depending on the encoder's precision
            // setting; what matters is that it arrives as a number, not "3500.0000".
            ->where('activeConversation.custom_fields.0.value', fn (mixed $value): bool => is_numeric($value) && (float) $value === 3500.0)
        );
});

test('a field with no value yet is exposed as null', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'matricula',
    ]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activeConversation.custom_fields', 1)
            ->where('activeConversation.custom_fields.0.value', null)
        );
});

test('another lead value never leaks into this conversation', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    $field = CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'renda',
        'type' => 'text',
    ]);
    CustomFieldValue::create([
        'custom_field_id' => $field->id,
        'entity_type' => 'lead',
        'entity_id' => $lead->id + 1000,
        'value_text' => 'do outro lead',
    ]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.custom_fields.0.value', null)
        );
});

test('fields defined by another tenant are not offered', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    $other = Tenant::create(['name' => 'ForeignPanelFields']);
    CustomField::factory()->create(['tenant_id' => (string) $other->id]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activeConversation.custom_fields', 0));
});

/**
 * `json` fields are written by agent tools (INSS ships `credito` and `documentos`),
 * so the panel shows them but refuses to let a human overwrite them by hand.
 */
test('json fields are marked read-only and rejected on write', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    CustomField::factory()->ofType('json')->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'credito',
        'label' => 'Dados de Crédito',
    ]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.custom_fields.0.editable', false)
        );

    $this->actingAs($owner)
        ->patch(route('conversas.custom-fields.update', $lead), [
            'values' => ['credito' => 'sobrescrito'],
        ])
        ->assertSessionHasErrors('values.credito');
});

test('an operator saves values for every editable type', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    $fields = collect([
        ['slug' => 'observacao', 'type' => 'text'],
        ['slug' => 'renda', 'type' => 'number'],
        ['slug' => 'nascimento', 'type' => 'date'],
        ['slug' => 'financiamento', 'type' => 'boolean'],
    ])->mapWithKeys(fn (array $spec) => [
        $spec['slug'] => CustomField::factory()->ofType($spec['type'])->create([
            'tenant_id' => (string) $tenant->id,
            'slug' => $spec['slug'],
        ]),
    ]);

    $faixa = CustomField::factory()
        ->select([['value' => 'alta', 'label' => 'Alta'], ['value' => 'baixa', 'label' => 'Baixa']])
        ->create(['tenant_id' => (string) $tenant->id, 'slug' => 'faixa']);

    $this->actingAs($owner)
        ->from(route('conversas.show', $lead))
        ->patch(route('conversas.custom-fields.update', $lead), [
            'values' => [
                'observacao' => 'Prefere ligação à tarde',
                'renda' => '3500.5',
                'nascimento' => '1975-04-09',
                'financiamento' => true,
                'faixa' => 'alta',
            ],
        ])
        ->assertRedirect(route('conversas.show', $lead));

    expect($lead->getCustomField('observacao'))->toBe('Prefere ligação à tarde')
        ->and((float) $lead->getCustomField('renda'))->toBe(3500.5)
        ->and($lead->getCustomField('nascimento')->toDateString())->toBe('1975-04-09')
        ->and($lead->getCustomField('financiamento'))->toBeTrue()
        ->and($lead->getCustomField('faixa'))->toBe('alta');

    expect(CustomFieldValue::query()->count())->toBe($fields->count() + 1)
        ->and($faixa->fresh()->slug)->toBe('faixa');
});

test('a blank value clears the field instead of failing validation', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    $field = CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'observacao',
        'type' => 'text',
    ]);
    CustomFieldValue::create([
        'custom_field_id' => $field->id,
        'entity_type' => 'lead',
        'entity_id' => $lead->id,
        'value_text' => 'algo antigo',
    ]);

    $this->actingAs($owner)
        ->patch(route('conversas.custom-fields.update', $lead), [
            'values' => ['observacao' => ''],
        ])
        ->assertSessionHasNoErrors();

    expect($lead->getCustomField('observacao'))->toBeNull();
});

/**
 * `is_required` is a display hint, not a gate: the panel collects information as
 * the conversation reveals it, so refusing to save what *is* known would be worse
 * than an unfilled field.
 */
test('a required field left blank still saves', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'cpf_conjuge',
        'is_required' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('conversas.custom-fields.update', $lead), [
            'values' => ['cpf_conjuge' => ''],
        ])
        ->assertSessionHasNoErrors();
});

test('a value outside the select options is refused', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    CustomField::factory()
        ->select([['value' => 'alta', 'label' => 'Alta']])
        ->create(['tenant_id' => (string) $tenant->id, 'slug' => 'faixa']);

    $this->actingAs($owner)
        ->patch(route('conversas.custom-fields.update', $lead), [
            'values' => ['faixa' => 'inventada'],
        ])
        ->assertSessionHasErrors('values.faixa');

    expect($lead->getCustomField('faixa'))->toBeNull();
});

test('a non numeric value on a number field is refused', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    CustomField::factory()->ofType('number')->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'renda',
    ]);

    $this->actingAs($owner)
        ->patch(route('conversas.custom-fields.update', $lead), [
            'values' => ['renda' => 'três mil'],
        ])
        ->assertSessionHasErrors('values.renda');
});

test('a slug the tenant never defined is refused rather than silently dropped', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    $this->actingAs($owner)
        ->patch(route('conversas.custom-fields.update', $lead), [
            'values' => ['campo_fantasma' => 'x'],
        ])
        ->assertSessionHasErrors('values.campo_fantasma');
});

/**
 * Filling a field is inbox work, like notes: an atendente writes what the customer
 * just told them without needing rights to define the field.
 */
test('a restricted member may fill the values', function (): void {
    [$tenant, $member, $lead] = panelCustomFieldsSetup(TenantRole::User);

    CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'observacao',
    ]);

    $this->actingAs($member)
        ->patch(route('conversas.custom-fields.update', $lead), [
            'values' => ['observacao' => 'Cliente pediu retorno segunda'],
        ])
        ->assertRedirect();

    expect($lead->getCustomField('observacao'))->toBe('Cliente pediu retorno segunda');
});

test('a member of another tenant cannot write values on the lead', function (): void {
    [$tenant, $owner, $lead] = panelCustomFieldsSetup();

    CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'observacao',
    ]);

    $other = Tenant::create(['name' => 'CustomFieldOutsider']);
    $outsider = User::factory()->create();
    $outsider->tenants()->detach();
    $outsider->tenants()->attach($other->id, ['role' => TenantRole::Owner->value]);

    // The Lead global tenant scope turns a cross-tenant id into a 404 at
    // route-model binding, before the request's own authorize() runs.
    $this->actingAs($outsider)
        ->patch(route('conversas.custom-fields.update', $lead), [
            'values' => ['observacao' => 'intruso'],
        ])
        ->assertNotFound();

    expect($lead->getCustomField('observacao'))->toBeNull();
});
