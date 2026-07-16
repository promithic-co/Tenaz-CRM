<?php

use App\Enums\TemplateKind;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeAuthUserWithMetaCloud(): array
{
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);

    return [$user, $instance];
}

test('index defaults to meta_hsm kind', function () {
    [$user] = makeAuthUserWithMetaCloud();

    $response = $this->actingAs($user)->get('/templates');

    $response->assertInertia(fn ($page) => $page
        ->component('templates/Index')
        ->where('currentKind', 'meta_hsm')
    );
});

test('store creates meta_hsm template', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    Http::fake(['*' => Http::response([
        'id' => 'tpl_abc',
        'status' => 'PENDING',
        'category' => 'MARKETING',
    ], 200)]);

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Campanha Meta',
        'meta_template_name' => 'campanha_meta',
        'body' => 'Olá {{1}}!',
        'variable_examples' => ['1' => 'Cliente Teste'],
        'category' => 'MARKETING',
        'language' => 'pt_BR',
    ]);

    $response->assertRedirect();

    $template = WhatsappTemplate::where('meta_template_id', 'tpl_abc')->first();
    expect($template)->not->toBeNull();
    expect($template->kind)->toBe(TemplateKind::MetaHsm);
    expect((string) $template->tenant_id)->toBe($user->tenantId);
    expect($template->status)->toBe('PENDING');
    expect($template->meta_waba_id)->toBe($instance->meta_waba_id);
});

test('store sends meta_hsm template to Meta using selected instance waba', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    Http::fake(['*' => Http::response(['id' => 'tpl_pending'], 200)]);

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Campanha Meta',
        'meta_template_name' => 'campanha_meta_manual_status',
        'body' => 'Ola {{1}}, seu protocolo e {{2}}.',
        'variable_examples' => ['1' => 'Cliente Teste', '2' => 'ABC123'],
        'category' => 'UTILITY',
        'language' => 'pt_BR',
    ]);

    $response->assertRedirect();

    Http::assertSent(fn ($request) => $request->data()['name'] === 'campanha_meta_manual_status'
        && str_contains($request->url(), (string) $instance->meta_waba_id)
        && $request->data()['category'] === 'UTILITY'
        && $request->data()['components'][0]['example']['body_text'][0] === ['Cliente Teste', 'ABC123']
    );

    $template = WhatsappTemplate::where('meta_template_name', 'campanha_meta_manual_status')->first();
    expect($template->status)->toBe('PENDING');
});

test('store rejects manual meta lifecycle fields', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    Http::fake();

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Campanha Meta',
        'meta_template_name' => 'campanha_meta_manual_fields',
        'body' => 'Ola {{1}}.',
        'variable_examples' => ['1' => 'Cliente Teste'],
        'status' => 'APPROVED',
        'meta_template_id' => 'tpl_manual',
        'meta_waba_id' => 'waba_manual',
        'category' => 'MARKETING',
        'language' => 'pt_BR',
    ]);

    $response->assertSessionHasErrors(['status', 'meta_template_id', 'meta_waba_id']);
    Http::assertNothingSent();
});

test('store rejects meta_hsm when selected instance is missing meta credentials', function () {
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'provider' => 'meta_cloud',
        'meta_waba_id' => null,
        'meta_access_token' => null,
    ]);

    Http::fake();

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Campanha Meta',
        'meta_template_name' => 'campanha_sem_credenciais',
        'body' => 'Ola mundo.',
        'category' => 'MARKETING',
        'language' => 'pt_BR',
    ]);

    $response->assertSessionHasErrors('whatsapp_instance_id');
    Http::assertNothingSent();
});

test('store rejects instance from another tenant', function () {
    [$user] = makeAuthUserWithMetaCloud();
    $otherUser = User::factory()->create();
    $otherInstance = WhatsappInstance::factory()->create([
        'user_id' => $otherUser->id,
        'tenant_id' => $otherUser->tenantId,
        'provider' => 'meta_cloud',
    ]);

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $otherInstance->id,
        'name' => 'Hack',
        'meta_template_name' => 'hack',
        'body' => 'Ola mundo.',
        'category' => 'MARKETING',
        'language' => 'pt_BR',
    ]);

    $response->assertSessionHasErrors('whatsapp_instance_id');
});

test('store creates a template with header, footer, and buttons', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    Http::fake(['*' => Http::response(['id' => 'tpl_full', 'status' => 'PENDING'], 200)]);

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Oferta Completa',
        'meta_template_name' => 'oferta_completa',
        'header_text' => 'Oferta para {{1}}',
        'header_example' => 'Lucas',
        'body' => 'Aproveite {{1}} hoje.',
        'variable_examples' => ['1' => 'sua oferta'],
        'footer_text' => 'Responda PARAR para sair.',
        'buttons' => [
            ['type' => 'QUICK_REPLY', 'text' => 'Tenho interesse'],
            ['type' => 'URL', 'text' => 'Ver mais', 'url' => 'https://example.com'],
        ],
        'category' => 'MARKETING',
        'language' => 'pt_BR',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $template = WhatsappTemplate::where('meta_template_name', 'oferta_completa')->first();
    expect($template->header)->toBe('Oferta para {{1}}')
        ->and($template->footer)->toBe('Responda PARAR para sair.')
        ->and($template->buttons_json)->toHaveCount(2);
});

test('store rejects a URL button without a url', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    Http::fake();

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Botao Quebrado',
        'meta_template_name' => 'botao_quebrado',
        'body' => 'Corpo simples.',
        'buttons' => [
            ['type' => 'URL', 'text' => 'Ver mais'],
        ],
        'category' => 'MARKETING',
        'language' => 'pt_BR',
    ]);

    $response->assertSessionHasErrors('buttons.0.url');
    Http::assertNothingSent();
});

test('store rejects a footer containing a variable', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    Http::fake();

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Rodape Quebrado',
        'meta_template_name' => 'rodape_quebrado',
        'body' => 'Corpo simples.',
        'footer_text' => 'Ate logo {{1}}',
        'category' => 'MARKETING',
        'language' => 'pt_BR',
    ]);

    $response->assertSessionHasErrors('footer_text');
    Http::assertNothingSent();
});

test('store rejects an unsupported language', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    Http::fake();

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Idioma Invalido',
        'meta_template_name' => 'idioma_invalido',
        'body' => 'Corpo simples.',
        'category' => 'MARKETING',
        'language' => 'en_US',
    ]);

    $response->assertSessionHasErrors('language');
    Http::assertNothingSent();
});

test('store persists a PHONE_NUMBER button with its phone number', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    Http::fake(['*' => Http::response(['id' => 'tpl_phone', 'status' => 'PENDING'], 200)]);

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Com Telefone',
        'meta_template_name' => 'com_telefone',
        'body' => 'Fale conosco.',
        'buttons' => [
            ['type' => 'PHONE_NUMBER', 'text' => 'Ligar agora', 'phone_number' => '5511999999999'],
        ],
        'category' => 'UTILITY',
        'language' => 'pt_BR',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $template = WhatsappTemplate::where('meta_template_name', 'com_telefone')->first();
    expect($template->buttons_json)->toBe([
        ['type' => 'PHONE_NUMBER', 'text' => 'Ligar agora', 'phone_number' => '5511999999999'],
    ]);
});

test('update changes template fields', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'kind' => 'meta_hsm',
        'name' => 'Original Name',
        'body' => 'Original body',
        'status' => 'PENDING',
    ]);

    $response = $this->actingAs($user)->put("/templates/{$template->id}", [
        'name' => 'Updated Name',
    ]);

    $response->assertRedirect();
    $template->refresh();
    expect($template->name)->toBe('Updated Name');
    expect($template->body)->toBe('Original body');
    expect($template->status)->toBe('PENDING');
});

test('update rejects synced meta fields', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'kind' => 'meta_hsm',
        'name' => 'Original Name',
        'body' => 'Original body',
        'status' => 'PENDING',
    ]);

    $response = $this->actingAs($user)->put("/templates/{$template->id}", [
        'body' => 'Updated body {{1}}',
        'status' => 'APPROVED',
        'category' => 'UTILITY',
        'language' => 'en',
        'meta_template_id' => 'tpl_manual',
        'meta_waba_id' => 'waba_manual',
    ]);

    $response->assertSessionHasErrors(['body', 'status', 'category', 'language', 'meta_template_id', 'meta_waba_id']);
    $template->refresh();
    expect($template->body)->toBe('Original body');
    expect($template->status)->toBe('PENDING');
});

test('update rejects instance reassignment', function () {
    [$user, $instance] = makeAuthUserWithMetaCloud();

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'kind' => 'meta_hsm',
        'name' => 'Original Name',
    ]);

    $other = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);

    $response = $this->actingAs($user)->put("/templates/{$template->id}", [
        'name' => 'Updated Name',
        'whatsapp_instance_id' => $other->id,
    ]);

    $response->assertSessionHasErrors('whatsapp_instance_id');
    expect($template->fresh()->whatsapp_instance_id)->toBe($instance->id);
});

test('update cannot access a template from another tenant', function () {
    [$user] = makeAuthUserWithMetaCloud();
    [$otherUser] = makeAuthUserWithMetaCloud();

    $otherTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $otherUser->tenantId,
        'kind' => 'meta_hsm',
        'name' => 'Template estrangeiro',
    ]);

    $response = $this->actingAs($user)->put("/templates/{$otherTemplate->id}", [
        'name' => 'Tentativa de alteracao',
    ]);

    $response->assertNotFound();
    $this->assertDatabaseHas('whatsapp_templates', [
        'id' => $otherTemplate->id,
        'name' => 'Template estrangeiro',
        'deleted_at' => null,
    ]);
});

test('destroy removes template without active campaigns', function () {
    [$user] = makeAuthUserWithMetaCloud();

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'kind' => 'meta_hsm',
    ]);

    $response = $this->actingAs($user)->delete("/templates/{$template->id}");

    $response->assertRedirect();
    expect(WhatsappTemplate::find($template->id))->toBeNull();
});

test('destroy cannot access a template from another tenant', function () {
    [$user] = makeAuthUserWithMetaCloud();
    [$otherUser] = makeAuthUserWithMetaCloud();

    $otherTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $otherUser->tenantId,
        'kind' => 'meta_hsm',
    ]);

    $response = $this->actingAs($user)->delete("/templates/{$otherTemplate->id}");

    $response->assertNotFound();
    $this->assertDatabaseHas('whatsapp_templates', [
        'id' => $otherTemplate->id,
        'deleted_at' => null,
    ]);
});
