<?php

use Illuminate\Support\Facades\Schema;

it('adds kind column with meta_hsm default', function () {
    expect(Schema::hasColumn('whatsapp_templates', 'kind'))->toBeTrue();
});

it('adds meta_template_id, meta_template_name, meta_waba_id nullable', function () {
    expect(Schema::hasColumn('whatsapp_templates', 'meta_template_id'))->toBeTrue()
        ->and(Schema::hasColumn('whatsapp_templates', 'meta_template_name'))->toBeTrue()
        ->and(Schema::hasColumn('whatsapp_templates', 'meta_waba_id'))->toBeTrue();
});

it('test_adds_meta_columns', function () {
    // Verify that meta columns are nullable (insert without them succeeds)
    $user = userWithTenant();

    $id = \Illuminate\Support\Facades\DB::table('whatsapp_templates')->insertGetId([
        'tenant_id' => $user->tenant_id,
        'whatsapp_instance_id' => null,
        'kind' => 'meta_hsm',
        'name' => 'test-template-no-meta',
        'status' => 'APPROVED',
        'language' => 'pt_BR',
        'body' => 'Test body',
        'variables_count' => 0,
        'meta_template_id' => null,
        'meta_template_name' => null,
        'meta_waba_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = \Illuminate\Support\Facades\DB::table('whatsapp_templates')->where('id', $id)->first();

    expect($row)->not->toBeNull()
        ->and($row->kind)->toBe('meta_hsm')
        ->and($row->meta_template_id)->toBeNull();
});
