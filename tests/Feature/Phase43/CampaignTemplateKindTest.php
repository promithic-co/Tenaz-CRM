<?php

use App\Enums\TemplateKind;
use App\Enums\WhatsAppProvider;
use App\Models\ContactList;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Campaign template kind filtering ─────────────────────────────────────────

test('campaign create page shows meta_hsm templates for meta_cloud instances', function () {
    $user = userWithTenant();

    $metaInstance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'provider' => WhatsAppProvider::MetaCloud,
    ]);

    WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'whatsapp_instance_id' => $metaInstance->id,
        'kind' => TemplateKind::MetaHsm->value,
        'name' => 'Meta HSM Template',
        'meta_template_name' => 'meta_hsm_template',
        'meta_waba_id' => $metaInstance->meta_waba_id,
    ]);

    $response = $this->actingAs($user)->get('/campanhas/create');

    $response->assertInertia(fn ($page) => $page
        ->component('campanhas/Create')
        ->has('templates', 1)
    );
});

test('templates have kind property exposed on campaign create page', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'provider' => WhatsAppProvider::MetaCloud,
    ]);

    WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'kind' => TemplateKind::MetaHsm->value,
        'name' => 'Meta Template',
        'meta_template_name' => 'meta_template',
        'meta_waba_id' => $instance->meta_waba_id,
    ]);

    $response = $this->actingAs($user)->get('/campanhas/create');

    $response->assertInertia(fn ($page) => $page
        ->component('campanhas/Create')
        ->has('templates.0.kind')
    );
});

test('campaign store rejects template that does not belong to tenant', function () {
    $user = userWithTenant();
    $otherUser = userWithTenant();

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'provider' => WhatsAppProvider::MetaCloud,
    ]);

    $foreignTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $otherUser->tenant_id,
        'kind' => TemplateKind::MetaHsm->value,
    ]);

    $contactList = ContactList::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $response = $this->actingAs($user)->post('/campanhas', [
        'name' => 'Test Campaign',
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $contactList->id,
        'whatsapp_template_id' => $foreignTemplate->id,
        'daily_limit' => 1000,
        'delay_between_ms' => 1000,
        'error_threshold_percent' => 10,
        'schedule_type' => 'now',
    ]);

    $response->assertSessionHasErrors('whatsapp_template_id');
});

// ─── Template kind on WhatsappTemplate model ───────────────────────────────────

test('template kind defaults to meta_hsm via database default', function () {
    $user = userWithTenant();

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        // kind not specified — should default to meta_hsm
    ]);

    $template->refresh();
    expect($template->kind)->toBe(TemplateKind::MetaHsm);
});

test('WhatsAppProvider enum has only MetaCloud case', function () {
    expect(WhatsAppProvider::cases())->toHaveCount(1);

    $values = array_map(fn ($c) => $c->value, WhatsAppProvider::cases());
    expect($values)->toBe(['meta_cloud']);
});

test('ofKind scope filters templates by meta_hsm kind', function () {
    $user = userWithTenant();

    WhatsappTemplate::factory()->create(['tenant_id' => $user->tenant_id, 'kind' => 'meta_hsm']);
    WhatsappTemplate::factory()->create(['tenant_id' => $user->tenant_id, 'kind' => 'meta_hsm']);

    $metaCount = WhatsappTemplate::ofKind(TemplateKind::MetaHsm)->count();

    expect($metaCount)->toBe(2);
});
