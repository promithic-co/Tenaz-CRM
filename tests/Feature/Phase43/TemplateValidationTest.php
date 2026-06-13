<?php

use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('store meta_hsm template requires builder fields', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Test Template',
    ]);

    $response->assertSessionHasErrors(['meta_template_name', 'body', 'category', 'language']);
});

test('store meta_hsm template succeeds with builder fields and meta response', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    Http::fake(['*' => Http::response(['id' => 'tpl_123', 'status' => 'PENDING'], 200)]);

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Campanha Janeiro',
        'meta_template_name' => 'campanha_janeiro',
        'body' => 'Ola {{1}}, sua proposta esta pronta!',
        'variable_examples' => ['1' => 'Cliente Teste'],
        'category' => 'MARKETING',
        'language' => 'pt_BR',
    ]);

    $response->assertRedirect();

    $template = WhatsappTemplate::withoutGlobalScope('tenant')->where('meta_template_id', 'tpl_123')->first();
    expect($template)->not->toBeNull();
    expect($template->kind->value)->toBe('meta_hsm');
    expect($template->meta_template_name)->toBe('campanha_janeiro');
    expect($template->meta_waba_id)->toBe($instance->meta_waba_id);
    expect($template->status)->toBe('PENDING');
    expect((string) $template->tenant_id)->toBe((string) $user->tenant_id);
});

test('store meta_hsm template rejects manual meta lifecycle fields', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    Http::fake();

    $response = $this->actingAs($user)->post('/templates', [
        'kind' => 'meta_hsm',
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Campanha Janeiro',
        'meta_template_name' => 'campanha_janeiro_manual',
        'meta_template_id' => 'tpl_manual',
        'meta_waba_id' => 'waba_manual',
        'body' => 'Ola {{1}}, sua proposta esta pronta!',
        'variable_examples' => ['1' => 'Cliente Teste'],
        'status' => 'APPROVED',
        'category' => 'MARKETING',
        'language' => 'pt_BR',
    ]);

    $response->assertSessionHasErrors(['meta_template_id', 'meta_waba_id', 'status']);
    Http::assertNothingSent();
});

test('index defaults to meta_hsm kind', function () {
    $user = userWithTenant();

    WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'kind' => 'meta_hsm',
        'name' => 'Meta Template',
    ]);

    $response = $this->actingAs($user)->get('/templates');

    $response->assertInertia(fn ($page) => $page
        ->component('templates/Index')
        ->where('currentKind', 'meta_hsm')
    );
});
