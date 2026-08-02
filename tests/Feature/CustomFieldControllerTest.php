<?php

use App\Enums\TenantRole;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Tenant, 1: User}
 */
function customFieldSetup(TenantRole $role = TenantRole::Owner): array
{
    $tenant = Tenant::create(['name' => 'CustomFieldsTest']);

    $actor = User::factory()->create();
    $actor->tenants()->detach();
    $actor->tenants()->attach($tenant->id, ['role' => $role->value]);

    return [$tenant, $actor];
}

test('the settings page lists the tenant fields in display order', function (): void {
    [$tenant, $owner] = customFieldSetup();

    CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'segundo',
        'label' => 'Segundo',
        'sort_order' => 1,
    ]);
    CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'primeiro',
        'label' => 'Primeiro',
        'sort_order' => 0,
    ]);

    $this->actingAs($owner)
        ->get(route('settings.campos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('configuracoes/campos/Index')
            ->has('fields', 2)
            ->where('fields.0.label', 'Primeiro')
            ->where('fields.1.label', 'Segundo')
            ->where('max_fields', CustomFieldService::MAX_FIELDS)
        );
});

test('fields from another tenant are not listed', function (): void {
    [$tenant, $owner] = customFieldSetup();

    $other = Tenant::create(['name' => 'OtherCustomFields']);
    CustomField::factory()->create(['tenant_id' => (string) $other->id]);

    $this->actingAs($owner)
        ->get(route('settings.campos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('fields', 0));
});

test('a restricted member cannot reach the settings page or mutate fields', function (): void {
    [$tenant, $member] = customFieldSetup(TenantRole::User);

    $field = CustomField::factory()->create(['tenant_id' => (string) $tenant->id]);

    $this->actingAs($member)->get(route('settings.campos.index'))->assertForbidden();
    $this->actingAs($member)
        ->post(route('settings.campos.store'), ['label' => 'Renda', 'type' => 'text'])
        ->assertForbidden();
    $this->actingAs($member)
        ->patch(route('settings.campos.update', $field), ['label' => 'Outro'])
        ->assertForbidden();
    $this->actingAs($member)
        ->delete(route('settings.campos.destroy', $field))
        ->assertForbidden();
});

test('creating a field derives a filter-safe slug from the label', function (): void {
    [$tenant, $owner] = customFieldSetup();

    $this->actingAs($owner)
        ->from(route('settings.campos.index'))
        ->post(route('settings.campos.store'), [
            'label' => 'Renda Mensal Líquida!',
            'type' => 'number',
        ])
        ->assertRedirect(route('settings.campos.index'));

    $field = CustomField::query()->where('tenant_id', (string) $tenant->id)->sole();

    // FilterSchema only accepts custom_field:<slug> as [a-z0-9_]+.
    expect($field->slug)->toBe('renda_mensal_liquida')
        ->and($field->slug)->toMatch('/^[a-z0-9_]+$/')
        ->and($field->type)->toBe('number')
        ->and($field->entity_type)->toBe('lead');
});

test('a second field with the same name is refused instead of silently suffixed', function (): void {
    [$tenant, $owner] = customFieldSetup();

    CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'renda',
        'label' => 'Renda',
    ]);

    $this->actingAs($owner)
        ->post(route('settings.campos.store'), ['label' => 'Renda', 'type' => 'text'])
        ->assertSessionHasErrors('label');

    expect(CustomField::query()->where('tenant_id', (string) $tenant->id)->count())->toBe(1);
});

test('a label with no usable characters is rejected', function (): void {
    [$tenant, $owner] = customFieldSetup();

    $this->actingAs($owner)
        ->post(route('settings.campos.store'), ['label' => '!!!', 'type' => 'text'])
        ->assertSessionHasErrors('label');

    expect(CustomField::query()->where('tenant_id', (string) $tenant->id)->count())->toBe(0);
});

test('a select field requires options and stores them with derived values', function (): void {
    [$tenant, $owner] = customFieldSetup();

    $this->actingAs($owner)
        ->post(route('settings.campos.store'), ['label' => 'Faixa', 'type' => 'select'])
        ->assertSessionHasErrors('options');

    $this->actingAs($owner)
        ->post(route('settings.campos.store'), [
            'label' => 'Faixa de Preço',
            'type' => 'select',
            'options' => [['label' => 'Até R$ 300k'], ['label' => 'Acima de R$ 300k']],
        ])
        ->assertRedirect();

    $field = CustomField::query()->where('slug', 'faixa_de_preco')->sole();

    expect($field->options)->toBe([
        ['value' => 'ate_r_300k', 'label' => 'Até R$ 300k'],
        ['value' => 'acima_de_r_300k', 'label' => 'Acima de R$ 300k'],
    ]);
});

test('the field cap is enforced', function (): void {
    [$tenant, $owner] = customFieldSetup();

    foreach (range(1, CustomFieldService::MAX_FIELDS) as $i) {
        CustomField::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'slug' => "campo_$i",
            'label' => "Campo $i",
        ]);
    }

    $this->actingAs($owner)
        ->post(route('settings.campos.store'), ['label' => 'Um mais', 'type' => 'text'])
        ->assertSessionHasErrors('label');
});

/**
 * The slug is referenced by saved smart-list filters and the type decides which
 * value column holds the data, so neither may be rewritten from the outside.
 */
test('updating a field cannot change its slug or type', function (): void {
    [$tenant, $owner] = customFieldSetup();

    $field = CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'renda',
        'label' => 'Renda',
        'type' => 'text',
    ]);

    $this->actingAs($owner)
        ->patch(route('settings.campos.update', $field), [
            'label' => 'Renda mensal',
            'is_required' => true,
            'slug' => 'outro_slug',
            'type' => 'number',
        ])
        ->assertRedirect();

    $field->refresh();

    expect($field->label)->toBe('Renda mensal')
        ->and($field->is_required)->toBeTrue()
        ->and($field->slug)->toBe('renda')
        ->and($field->type)->toBe('text');
});

test('deleting a field removes the values stored for it', function (): void {
    [$tenant, $owner] = customFieldSetup();

    $field = CustomField::factory()->create(['tenant_id' => (string) $tenant->id]);
    CustomFieldValue::create([
        'custom_field_id' => $field->id,
        'entity_type' => 'lead',
        'entity_id' => 999,
        'value_text' => 'algo',
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.campos.destroy', $field))
        ->assertRedirect();

    expect(CustomField::query()->find($field->id))->toBeNull()
        ->and(CustomFieldValue::query()->where('custom_field_id', $field->id)->exists())->toBeFalse();
});

test('the settings page reports how many leads already filled each field', function (): void {
    [$tenant, $owner] = customFieldSetup();

    $field = CustomField::factory()->create(['tenant_id' => (string) $tenant->id]);
    foreach ([11, 12] as $leadId) {
        CustomFieldValue::create([
            'custom_field_id' => $field->id,
            'entity_type' => 'lead',
            'entity_id' => $leadId,
            'value_text' => 'algo',
        ]);
    }

    $this->actingAs($owner)
        ->get(route('settings.campos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('fields.0.values_count', 2));
});

test('a field belonging to another tenant is a 404, not a 403', function (): void {
    [$tenant, $owner] = customFieldSetup();

    $other = Tenant::create(['name' => 'ForeignCustomFields']);
    $foreign = CustomField::factory()->create([
        'tenant_id' => (string) $other->id,
        'label' => 'Alheio',
    ]);

    // custom_fields carries no global tenant scope, so the controller has to make
    // this call itself; a 403 would confirm the row exists.
    $this->actingAs($owner)
        ->patch(route('settings.campos.update', $foreign), ['label' => 'Roubado'])
        ->assertNotFound();
    $this->actingAs($owner)
        ->delete(route('settings.campos.destroy', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->label)->toBe('Alheio');
});

test('reordering rewrites sort_order and ignores foreign ids', function (): void {
    [$tenant, $owner] = customFieldSetup();

    $first = CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'a',
        'sort_order' => 0,
    ]);
    $second = CustomField::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'slug' => 'b',
        'sort_order' => 1,
    ]);

    $other = Tenant::create(['name' => 'ReorderOutsider']);
    $foreign = CustomField::factory()->create([
        'tenant_id' => (string) $other->id,
        'sort_order' => 7,
    ]);

    $this->actingAs($owner)
        ->post(route('settings.campos.reorder'), [
            'ids' => [$second->id, $foreign->id, $first->id],
        ])
        ->assertRedirect();

    expect($second->fresh()->sort_order)->toBe(0)
        ->and($first->fresh()->sort_order)->toBe(1)
        ->and($foreign->fresh()->sort_order)->toBe(7);
});
