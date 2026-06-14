<?php

use App\Models\WhatsappInstance;
use App\Services\WhatsApp\MetaTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('createAndStoreTemplate filters blanks, orders examples, and persists the record', function (): void {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    Http::fake(['*' => Http::response(['id' => 'tpl_xyz', 'status' => 'PENDING'], 200)]);

    $template = app(MetaTemplateService::class)->createAndStoreTemplate(
        instance: $instance,
        tenantId: (string) $user->tenant_id,
        internalName: 'Campanha Junho',
        metaName: 'campanha_junho',
        category: 'UTILITY',
        language: 'pt_BR',
        spec: [
            'body' => 'Ola {{1}}, protocolo {{2}}.',
            // Out of order, with a blank that must be dropped before ksort.
            'variable_examples' => ['2' => 'ABC123', '1' => 'Cliente Teste', '3' => ''],
        ],
    );

    Http::assertSent(fn ($request) => $request->data()['name'] === 'campanha_junho'
        && str_contains($request->url(), (string) $instance->meta_waba_id)
        && $request->data()['components'][0]['type'] === 'BODY'
        && $request->data()['components'][0]['example']['body_text'][0] === ['Cliente Teste', 'ABC123']
    );

    expect($template->meta_template_id)->toBe('tpl_xyz')
        ->and($template->status)->toBe('PENDING')
        ->and($template->variables_count)->toBe(2)
        ->and($template->header)->toBeNull()
        ->and($template->footer)->toBeNull()
        ->and($template->buttons_json)->toBeNull();
});

test('createAndStoreTemplate builds header, footer, and buttons components in Meta order', function (): void {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    Http::fake(['*' => Http::response(['id' => 'tpl_full', 'status' => 'PENDING'], 200)]);

    $template = app(MetaTemplateService::class)->createAndStoreTemplate(
        instance: $instance,
        tenantId: (string) $user->tenant_id,
        internalName: 'Oferta',
        metaName: 'oferta_junho',
        category: 'MARKETING',
        language: 'pt_BR',
        spec: [
            'header_text' => 'Oferta para {{1}}',
            'header_example' => 'Lucas',
            'body' => 'Aproveite {{1}}.',
            'variable_examples' => ['1' => 'sua oferta'],
            'footer_text' => 'Responda PARAR para sair.',
            'buttons' => [
                ['type' => 'QUICK_REPLY', 'text' => 'Tenho interesse'],
                ['type' => 'URL', 'text' => 'Ver detalhes', 'url' => 'https://example.com/oferta'],
                ['type' => 'PHONE_NUMBER', 'text' => 'Ligar', 'phone_number' => '5511999999999'],
                // Dropped: missing url.
                ['type' => 'URL', 'text' => 'Quebrado'],
            ],
        ],
    );

    Http::assertSent(function ($request) {
        $components = $request->data()['components'];

        return $components[0]['type'] === 'HEADER'
            && $components[0]['format'] === 'TEXT'
            && $components[0]['example']['header_text'] === ['Lucas']
            && $components[1]['type'] === 'BODY'
            && $components[2]['type'] === 'FOOTER'
            && $components[3]['type'] === 'BUTTONS'
            && count($components[3]['buttons']) === 3
            && $components[3]['buttons'][1] === ['type' => 'URL', 'text' => 'Ver detalhes', 'url' => 'https://example.com/oferta'];
    });

    expect($template->header)->toBe('Oferta para {{1}}')
        ->and($template->footer)->toBe('Responda PARAR para sair.')
        ->and($template->buttons_json)->toHaveCount(3);
});
