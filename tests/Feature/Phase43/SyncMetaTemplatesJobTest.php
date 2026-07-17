<?php

use App\Jobs\SyncMetaTemplatesJob;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
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

it('serializes sync jobs per instance with explicit lock timing', function () {
    $middleware = (new SyncMetaTemplatesJob(42))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe('meta_template_sync_instance:42')
        ->and($middleware[0]->expiresAfter)->toBe(180)
        ->and($middleware[0]->releaseAfter)->toBe(90);
});

it('releases the sync job when its instance lock is unavailable', function () {
    $job = new SyncMetaTemplatesJob(42);
    $middleware = $job->middleware()[0];
    $lock = Cache::lock($middleware->getLockKey($job), 180);

    expect($lock->get())->toBeTrue();

    try {
        $fakeJob = new FakeJob;
        $job->setJob($fakeJob);
        $handled = false;

        $middleware->handle($job, function () use (&$handled): void {
            $handled = true;
        });

        expect($handled)->toBeFalse()
            ->and($fakeJob->isReleased())->toBeTrue()
            ->and($fakeJob->releaseDelay)->toBe(90);
    } finally {
        $lock->release();
    }
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

it('stamps every persisted page with one immutable sync cycle timestamp', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    $cycleStartedAt = now()->startOfSecond();
    $this->travelTo($cycleStartedAt);

    Http::fakeSequence()
        ->push([
            'data' => [[
                'id' => 'tpl_page_one',
                'name' => 'cycle_page_one',
                'status' => 'APPROVED',
                'category' => 'MARKETING',
                'language' => 'pt_BR',
                'components' => [],
            ]],
            'paging' => ['next' => 'https://graph.facebook.com/v23.0/next'],
        ], 200)
        ->push([
            'data' => [[
                'id' => 'tpl_page_two',
                'name' => 'cycle_page_two',
                'status' => 'APPROVED',
                'category' => 'UTILITY',
                'language' => 'en_US',
                'components' => [],
            ]],
            'paging' => [],
        ], 200);

    (new SyncMetaTemplatesJob($instance->id))->handle();

    $syncedAtValues = WhatsappTemplate::query()
        ->where('whatsapp_instance_id', $instance->id)
        ->orderBy('meta_template_name')
        ->pluck('last_synced_at');

    expect($syncedAtValues)->toHaveCount(2);

    foreach ($syncedAtValues as $syncedAt) {
        expect($syncedAt)->not->toBeNull()
            ->and($syncedAt->equalTo($cycleStartedAt))->toBeTrue();
    }
});

it('does not let an older sync cycle overwrite newer template state or cache', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);
    $newerCycleAt = now()->startOfSecond();

    Http::fakeSequence()
        ->push(['data' => [[
            'id' => 'tpl_newer_provider_state',
            'name' => 'monotonic_template',
            'status' => 'APPROVED',
            'category' => 'UTILITY',
            'language' => 'pt_BR',
            'components' => [
                ['type' => 'BODY', 'text' => 'Newer provider body'],
            ],
        ]], 'paging' => []], 200)
        ->push(['data' => [[
            'id' => 'tpl_stale_provider_state',
            'name' => 'monotonic_template',
            'status' => 'REJECTED',
            'category' => 'MARKETING',
            'language' => 'pt_BR',
            'components' => [
                ['type' => 'BODY', 'text' => 'Stale provider body'],
            ],
        ]], 'paging' => []], 200);

    $this->travelTo($newerCycleAt);

    (new SyncMetaTemplatesJob($instance->id))->handle();

    $template = WhatsappTemplate::query()
        ->where('whatsapp_instance_id', $instance->id)
        ->where('meta_template_name', 'monotonic_template')
        ->firstOrFail();
    $cacheKey = "whatsapp_send_template:{$template->id}";
    Cache::put($cacheKey, ['state' => 'newer'], 60);

    $this->travelTo($newerCycleAt->copy()->subMinute());

    (new SyncMetaTemplatesJob($instance->id))->handle();

    $template->refresh();

    expect($template->last_synced_at->equalTo($newerCycleAt))->toBeTrue()
        ->and($template->meta_template_id)->toBe('tpl_newer_provider_state')
        ->and($template->status)->toBe('APPROVED')
        ->and($template->category)->toBe('UTILITY')
        ->and($template->body)->toBe('Newer provider body')
        ->and($template->components_json)->toBe([
            ['type' => 'BODY', 'text' => 'Newer provider body'],
        ])
        ->and(Cache::get($cacheKey))->toBe(['state' => 'newer']);
});

it('keeps the first writer when divergent sync cycles share the same timestamp second', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);
    $sharedCycleAt = now()->startOfSecond();

    Http::fakeSequence()
        ->push(['data' => [[
            'id' => 'tpl_same_second_first',
            'name' => 'same_second_template',
            'status' => 'APPROVED',
            'category' => 'UTILITY',
            'language' => 'pt_BR',
            'components' => [
                ['type' => 'BODY', 'text' => 'First writer body'],
            ],
        ]], 'paging' => []], 200)
        ->push(['data' => [[
            'id' => 'tpl_same_second_second',
            'name' => 'same_second_template',
            'status' => 'REJECTED',
            'category' => 'MARKETING',
            'language' => 'pt_BR',
            'components' => [
                ['type' => 'BODY', 'text' => 'Second writer body'],
            ],
        ]], 'paging' => []], 200);

    $this->travelTo($sharedCycleAt);
    (new SyncMetaTemplatesJob($instance->id))->handle();

    $template = WhatsappTemplate::query()
        ->where('whatsapp_instance_id', $instance->id)
        ->where('meta_template_name', 'same_second_template')
        ->firstOrFail();
    $cacheKey = "whatsapp_send_template:{$template->id}";
    Cache::put($cacheKey, ['state' => 'first-writer'], 60);

    (new SyncMetaTemplatesJob($instance->id))->handle();

    $template->refresh();

    expect($template->last_synced_at->equalTo($sharedCycleAt))->toBeTrue()
        ->and($template->meta_template_id)->toBe('tpl_same_second_first')
        ->and($template->status)->toBe('APPROVED')
        ->and($template->category)->toBe('UTILITY')
        ->and($template->body)->toBe('First writer body')
        ->and($template->components_json)->toBe([
            ['type' => 'BODY', 'text' => 'First writer body'],
        ])
        ->and(Cache::get($cacheKey))->toBe(['state' => 'first-writer']);
});

it('applies a newer sync cycle and invalidates the changed template cache', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);
    $olderCycleAt = now()->startOfSecond();

    Http::fakeSequence()
        ->push(['data' => [[
            'id' => 'tpl_initial_state',
            'name' => 'monotonic_forward_template',
            'status' => 'PENDING',
            'category' => 'MARKETING',
            'language' => 'pt_BR',
            'components' => [],
        ]], 'paging' => []], 200)
        ->push(['data' => [[
            'id' => 'tpl_updated_state',
            'name' => 'monotonic_forward_template',
            'status' => 'APPROVED',
            'category' => 'UTILITY',
            'language' => 'pt_BR',
            'components' => [],
        ]], 'paging' => []], 200);

    $this->travelTo($olderCycleAt);
    (new SyncMetaTemplatesJob($instance->id))->handle();

    $template = WhatsappTemplate::query()
        ->where('whatsapp_instance_id', $instance->id)
        ->where('meta_template_name', 'monotonic_forward_template')
        ->firstOrFail();
    $cacheKey = "whatsapp_send_template:{$template->id}";
    Cache::put($cacheKey, ['state' => 'older'], 60);

    $newerCycleAt = $olderCycleAt->copy()->addMinute();
    $this->travelTo($newerCycleAt);
    (new SyncMetaTemplatesJob($instance->id))->handle();

    $template->refresh();

    expect($template->last_synced_at->equalTo($newerCycleAt))->toBeTrue()
        ->and($template->meta_template_id)->toBe('tpl_updated_state')
        ->and($template->status)->toBe('APPROVED')
        ->and(Cache::get($cacheKey))->toBeNull();
});

it('does not stamp a template when its provider page fails', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'kind' => 'meta_hsm',
        'name' => 'Internal label',
        'meta_template_name' => 'provider_failure',
        'language' => 'pt_BR',
        'last_synced_at' => null,
    ]);

    Http::fake(['*' => Http::response([], 500)]);

    (new SyncMetaTemplatesJob($instance->id))->handle();

    expect($template->fresh()->last_synced_at)->toBeNull();
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

it('remains idempotent when overlap protection is bypassed or unavailable', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);
    $templatePayload = [
        'id' => 'tpl_database_authoritative',
        'name' => 'database_authoritative',
        'status' => 'APPROVED',
        'category' => 'MARKETING',
        'language' => 'pt_BR',
        'components' => [],
    ];

    Http::fake(['*' => Http::response(['data' => [$templatePayload], 'paging' => []], 200)]);

    (new SyncMetaTemplatesJob($instance->id))->handle();
    (new SyncMetaTemplatesJob($instance->id))->handle();

    expect(WhatsappTemplate::query()
        ->where('tenant_id', $instance->tenant_id)
        ->where('whatsapp_instance_id', $instance->id)
        ->where('kind', 'meta_hsm')
        ->where('meta_template_name', 'database_authoritative')
        ->where('language', 'pt_BR')
        ->count())->toBe(1);
});

it('fails deterministically when another unique key conflicts with a sync insert', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);

    $existingTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'kind' => 'meta_hsm',
        'name' => 'internal_name_collision',
        'meta_template_name' => 'different_meta_identity',
        'language' => 'pt_BR',
        'meta_template_id' => 'original_provider_id',
        'status' => 'PENDING',
        'body' => 'Original body',
    ]);

    Http::fake(['*' => Http::response(['data' => [[
        'id' => 'tpl_unique_conflict',
        'name' => 'internal_name_collision',
        'status' => 'APPROVED',
        'category' => 'MARKETING',
        'language' => 'pt_BR',
        'components' => [],
    ]], 'paging' => []], 200)]);

    expect(fn () => (new SyncMetaTemplatesJob($instance->id))->handle())
        ->toThrow(RuntimeException::class, "Meta template sync identity conflict for instance {$instance->id}");

    expect(WhatsappTemplate::query()
        ->where('whatsapp_instance_id', $instance->id)
        ->count())->toBe(1);

    $existingTemplate->refresh();

    expect($existingTemplate->meta_template_name)->toBe('different_meta_identity')
        ->and($existingTemplate->meta_template_id)->toBe('original_provider_id')
        ->and($existingTemplate->status)->toBe('PENDING')
        ->and($existingTemplate->body)->toBe('Original body');
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
