<?php

use App\Models\Agent;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Lead;
use App\Models\NicheTemplate;
use App\Models\PromptTemplate;
use App\Models\StatusMachine;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create(['user_id' => $this->user->id]);
    $this->tenantId = $this->user->tenantId;
});

// ─── Prompt Templates ────────────────────────────────────────────────────────

test('prompt template renders variables', function () {
    $template = PromptTemplate::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Test',
        'slug' => 'test',
        'type' => 'system',
        'content' => 'Hello {{name}}, your credit is R${{value}}.',
        'is_active' => true,
    ]);

    expect($template->render(['name' => 'João', 'value' => '5.000']))
        ->toBe('Hello João, your credit is R$5.000.');
});

test('prompt template saveNewVersion increments version and deactivates old', function () {
    $original = PromptTemplate::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Test',
        'slug' => 'versioned',
        'type' => 'system',
        'content' => 'v1 content',
        'version' => 1,
        'is_active' => true,
    ]);

    $newVersion = $original->saveNewVersion(['content' => 'v2 content']);

    $original->refresh();
    expect($original->is_active)->toBeFalse()
        ->and($newVersion->version)->toBe(2)
        ->and($newVersion->is_active)->toBeTrue()
        ->and($newVersion->content)->toBe('v2 content');
});

// ─── Status Machine ───────────────────────────────────────────────────────────

test('status machine canTransition validates correctly', function () {
    $machine = StatusMachine::default();

    expect($machine->canTransition('novo', 'qualificado'))->toBeTrue()
        ->and($machine->canTransition('novo', 'convertido'))->toBeFalse()
        ->and($machine->canTransition('qualificado', 'escalado'))->toBeTrue();
});

test('status machine getTerminalStatuses returns terminal slugs', function () {
    $machine = StatusMachine::default();

    expect($machine->getTerminalStatuses())
        ->toContain('convertido')
        ->toContain('optou_sair')
        ->not->toContain('novo');
});

test('status machine forTenant falls back to default when none configured', function () {
    $machine = StatusMachine::forTenant('non-existent-tenant');

    expect($machine->initial_status)->toBe('novo')
        ->and($machine->getStatuses())->toHaveCount(7);
});

test('lead canTransitionTo uses status machine', function () {
    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenantId,
        'status' => 'novo',
    ]);

    expect($lead->canTransitionTo('qualificado'))->toBeTrue()
        ->and($lead->canTransitionTo('convertido'))->toBeFalse();
});

// ─── Custom Fields ────────────────────────────────────────────────────────────

test('custom field value can be stored and retrieved', function () {
    CustomField::create([
        'tenant_id' => $this->tenantId,
        'entity_type' => 'lead',
        'slug' => 'faixa_preco',
        'label' => 'Faixa de Preço',
        'type' => 'text',
    ]);

    $lead = Lead::factory()->create(['tenant_id' => $this->tenantId]);
    $lead->setCustomField('faixa_preco', '300_500k');

    expect($lead->getCustomField('faixa_preco'))->toBe('300_500k');
});

test('custom field value is stored in correct typed column', function () {
    CustomField::create([
        'tenant_id' => $this->tenantId,
        'entity_type' => 'lead',
        'slug' => 'valor_imovel',
        'label' => 'Valor do Imóvel',
        'type' => 'number',
    ]);

    $lead = Lead::factory()->create(['tenant_id' => $this->tenantId]);
    $lead->setCustomField('valor_imovel', 450000.00);

    $value = CustomFieldValue::whereHas('customField', fn ($q) => $q->where('slug', 'valor_imovel'))
        ->where('entity_id', $lead->id)
        ->first();

    expect($value->value_number)->not->toBeNull();
});

// ─── Niche Template ───────────────────────────────────────────────────────────

test('niche template apply creates status machine', function () {
    $template = NicheTemplate::create([
        'slug' => 'test-niche',
        'name' => 'Test Niche',
        'status_machine' => [
            'initial_status' => 'lead',
            'statuses' => [
                ['slug' => 'lead', 'label' => 'Lead', 'color' => 'gray', 'is_terminal' => false],
                ['slug' => 'fechado', 'label' => 'Fechado', 'color' => 'green', 'is_terminal' => true],
            ],
            'transitions' => [
                ['from' => 'lead', 'to' => 'fechado'],
            ],
        ],
        'custom_fields' => [],
        'prompt_templates' => [],
        'tool_definitions' => [],
    ]);

    $template->apply($this->tenantId);

    $machine = StatusMachine::where('tenant_id', $this->tenantId)->first();
    expect($machine)->not->toBeNull()
        ->and($machine->initial_status)->toBe('lead')
        ->and($machine->canTransition('lead', 'fechado'))->toBeTrue();
});

test('niche template apply creates custom fields', function () {
    $template = NicheTemplate::create([
        'slug' => 'test-niche-2',
        'name' => 'Test Niche 2',
        'custom_fields' => [
            ['slug' => 'interesse', 'label' => 'Interesse', 'type' => 'text', 'is_required' => false, 'sort_order' => 0],
        ],
        'status_machine' => null,
        'prompt_templates' => [],
        'tool_definitions' => [],
    ]);

    $template->apply($this->tenantId);

    expect(CustomField::forTenant($this->tenantId)->where('slug', 'interesse')->exists())->toBeTrue();
});

test('apply-template command applies niche template', function () {
    NicheTemplate::create([
        'slug' => 'cmd-test',
        'name' => 'Command Test',
        'custom_fields' => [],
        'status_machine' => null,
        'prompt_templates' => [],
        'tool_definitions' => [],
    ]);

    $this->artisan('credflow:apply-template', ['slug' => 'cmd-test', 'tenant_id' => $this->tenantId])
        ->assertSuccessful();
});

test('apply-template command fails for unknown slug', function () {
    $this->artisan('credflow:apply-template', ['slug' => 'does-not-exist', 'tenant_id' => $this->tenantId])
        ->assertFailed();
});
