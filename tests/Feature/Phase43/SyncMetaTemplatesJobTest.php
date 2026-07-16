<?php

use App\Jobs\SyncMetaTemplatesJob;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('skips when instance id does not exist', function () {
    Http::fake();

    (new SyncMetaTemplatesJob(99999))->handle();

    Http::assertNothingSent();
});

it('skips when Meta temporary token is expired', function () {
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_token_permanent' => false,
        'meta_token_expires_at' => now()->subMinute(),
    ]);

    Http::fake();

    (new SyncMetaTemplatesJob($instance->id))->handle();

    Http::assertNothingSent();
});

it('syncs Meta templates with encrypted token + 60s timeout', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'meta_access_token' => 'my-secret-token',
    ]);

    Http::fake(['*' => Http::response(['data' => [], 'paging' => []], 200)]);

    (new SyncMetaTemplatesJob($instance->id))->handle();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'message_templates')
            && $request->hasHeader('Authorization', 'Bearer my-secret-token');
    });
});

it('has a retry window for provider throttle releases', function () {
    $job = new SyncMetaTemplatesJob(1);

    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class);
    expect($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp());
    expect($job->maxExceptions)->toBe(3);
});

it('honors Meta retry-after when template sync is rate limited', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    Http::fake(['*' => Http::response([], 429, ['Retry-After' => '123'])]);

    $fakeJob = new FakeJob;
    $job = (new SyncMetaTemplatesJob($instance->id))->setJob($fakeJob);
    $job->handle();

    expect($fakeJob->isReleased())->toBeTrue();
    expect($fakeJob->releaseDelay)->toBe(123);
});

it('paginates Graph API results', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    $tpl1 = ['id' => 'tpl_1', 'name' => 'template_page_one', 'status' => 'APPROVED', 'category' => 'MARKETING', 'language' => 'pt_BR', 'components' => []];
    $tpl2 = ['id' => 'tpl_2', 'name' => 'template_page_two', 'status' => 'APPROVED', 'category' => 'MARKETING', 'language' => 'pt_BR', 'components' => []];

    Http::fakeSequence()
        ->push(['data' => [$tpl1], 'paging' => ['next' => 'https://graph.facebook.com/v23.0/next']], 200)
        ->push(['data' => [$tpl2], 'paging' => []], 200);

    (new SyncMetaTemplatesJob($instance->id))->handle();

    Http::assertSentCount(2);
    expect(WhatsappTemplate::where('kind', 'meta_hsm')->count())->toBe(2);
});

it('test_sync_meta_templates_job_upserts_templates', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    $templates = [
        ['id' => 'tpl_a', 'name' => 'template_alpha', 'status' => 'APPROVED', 'category' => 'MARKETING', 'language' => 'pt_BR', 'components' => []],
        ['id' => 'tpl_b', 'name' => 'template_beta', 'status' => 'APPROVED', 'category' => 'UTILITY', 'language' => 'pt_BR', 'components' => []],
        ['id' => 'tpl_c', 'name' => 'template_gamma', 'status' => 'PENDING', 'category' => 'MARKETING', 'language' => 'en', 'components' => []],
    ];

    Http::fake(['*' => Http::response(['data' => $templates, 'paging' => []], 200)]);
    (new SyncMetaTemplatesJob($instance->id))->handle();
    expect(WhatsappTemplate::where('kind', 'meta_hsm')->count())->toBe(3);

    // Second run with same data must not duplicate
    Http::fake(['*' => Http::response(['data' => $templates, 'paging' => []], 200)]);
    (new SyncMetaTemplatesJob($instance->id))->handle();
    expect(WhatsappTemplate::where('kind', 'meta_hsm')->count())->toBe(3);
});

it('matches an authored row by meta_template_name and keeps its internal name', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    // Authoring stores a user-facing internal `name` distinct from Meta's `meta_template_name`.
    $authored = WhatsappTemplate::factory()->create([
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'kind' => 'meta_hsm',
        'name' => 'Boas Vindas',
        'meta_template_name' => 'boas_vindas',
        'language' => 'pt_BR',
        'status' => 'PENDING',
    ]);

    Http::fake(['*' => Http::response(['data' => [[
        'id' => 'tpl_bv',
        'name' => 'boas_vindas',
        'status' => 'APPROVED',
        'category' => 'MARKETING',
        'language' => 'pt_BR',
        'components' => [],
    ]], 'paging' => []], 200)]);

    (new SyncMetaTemplatesJob($instance->id))->handle();

    expect(WhatsappTemplate::where('kind', 'meta_hsm')->count())->toBe(1);

    $fresh = $authored->fresh();
    expect($fresh->name)->toBe('Boas Vindas')
        ->and($fresh->status)->toBe('APPROVED')
        ->and($fresh->meta_template_id)->toBe('tpl_bv');
});

it('keeps distinct rows per language for the same meta_template_name', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    $templates = [
        ['id' => 'tpl_pt', 'name' => 'welcome', 'status' => 'APPROVED', 'category' => 'MARKETING', 'language' => 'pt_BR', 'components' => []],
        ['id' => 'tpl_en', 'name' => 'welcome', 'status' => 'APPROVED', 'category' => 'MARKETING', 'language' => 'en_US', 'components' => []],
    ];

    Http::fake(['*' => Http::response(['data' => $templates, 'paging' => []], 200)]);
    (new SyncMetaTemplatesJob($instance->id))->handle();
    expect(WhatsappTemplate::where('meta_template_name', 'welcome')->count())->toBe(2);

    // Re-sync must not duplicate either language.
    Http::fake(['*' => Http::response(['data' => $templates, 'paging' => []], 200)]);
    (new SyncMetaTemplatesJob($instance->id))->handle();
    expect(WhatsappTemplate::where('meta_template_name', 'welcome')->count())->toBe(2);
});

it('test_sync_meta_templates_job_extracts_variables_count', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    Http::fake(['*' => Http::response([
        'data' => [[
            'id' => 'tpl_vars',
            'name' => 'template_with_vars',
            'status' => 'APPROVED',
            'category' => 'MARKETING',
            'language' => 'pt_BR',
            'components' => [
                ['type' => 'BODY', 'text' => 'Olá {{1}}, sua oferta é {{2}}'],
            ],
        ]],
        'paging' => [],
    ], 200)]);

    (new SyncMetaTemplatesJob($instance->id))->handle();

    $template = WhatsappTemplate::where('name', 'template_with_vars')->first();
    expect($template->variables_count)->toBe(2);
});

it('syncs Meta template components and counts variables outside body', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    $buttons = [
        ['type' => 'QUICK_REPLY', 'text' => 'Tenho interesse'],
        ['type' => 'URL', 'text' => 'Ver detalhes', 'url' => 'https://example.com'],
    ];
    $components = [
        ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Oferta {{1}}'],
        ['type' => 'BODY', 'text' => 'Olá {{2}}, use {{3}}'],
        ['type' => 'FOOTER', 'text' => 'Rodapé'],
        ['type' => 'BUTTONS', 'buttons' => $buttons],
    ];

    Http::fake(['*' => Http::response([
        'data' => [[
            'id' => 'tpl_components',
            'name' => 'template_with_header_vars',
            'status' => 'REJECTED',
            'category' => 'MARKETING',
            'language' => 'pt_BR',
            'quality_score' => ['score' => 'RED'],
            'rejected_reason' => 'INVALID_FORMAT',
            'components' => $components,
        ]],
        'paging' => [],
    ], 200)]);

    (new SyncMetaTemplatesJob($instance->id))->handle();

    $template = WhatsappTemplate::where('name', 'template_with_header_vars')->first();
    expect($template->components_json)->toBe($components);
    expect($template->variables_count)->toBe(3);
    expect($template->header)->toBe('Oferta {{1}}');
    expect($template->footer)->toBe('Rodapé');
    expect($template->buttons_json)->toBe($buttons);
    expect($template->quality_score)->toBe('RED');
    expect($template->rejected_reason)->toBe('INVALID_FORMAT');
});

it('test_templates_sync_button_dispatches_job', function () {
    Bus::fake([SyncMetaTemplatesJob::class]);

    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    $this->actingAs($user)->post('/templates/sync-meta', [
        'whatsapp_instance_id' => $instance->id,
    ]);

    Bus::assertDispatched(SyncMetaTemplatesJob::class,
        fn ($job) => $job->instanceId === $instance->id
    );
});
