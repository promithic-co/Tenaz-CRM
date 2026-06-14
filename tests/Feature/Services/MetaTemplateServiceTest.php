<?php

use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\MetaTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('createAndStoreBodyTemplate filters blanks, orders examples, and persists the record', function (): void {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    Http::fake(['*' => Http::response(['id' => 'tpl_xyz', 'status' => 'PENDING'], 200)]);

    $template = app(MetaTemplateService::class)->createAndStoreBodyTemplate(
        instance: $instance,
        tenantId: (string) $user->tenant_id,
        internalName: 'Campanha Junho',
        metaName: 'campanha_junho',
        category: 'UTILITY',
        language: 'pt_BR',
        body: 'Ola {{1}}, protocolo {{2}}.',
        // Intentionally out of order, with a blank that must be dropped before ksort.
        variableExamples: ['2' => 'ABC123', '1' => 'Cliente Teste', '3' => ''],
    );

    Http::assertSent(fn ($request) => $request->data()['name'] === 'campanha_junho'
        && str_contains($request->url(), (string) $instance->meta_waba_id)
        && $request->data()['components'][0]['example']['body_text'][0] === ['Cliente Teste', 'ABC123']
    );

    expect($template)->toBeInstanceOf(WhatsappTemplate::class)
        ->and($template->meta_template_id)->toBe('tpl_xyz')
        ->and($template->status)->toBe('PENDING')
        ->and($template->variables_count)->toBe(2)
        ->and($template->meta_waba_id)->toBe($instance->meta_waba_id)
        ->and((string) $template->tenant_id)->toBe((string) $user->tenant_id);
});
