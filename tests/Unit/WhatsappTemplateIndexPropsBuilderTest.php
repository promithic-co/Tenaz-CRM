<?php

use App\Enums\TemplateKind;
use App\Enums\WhatsAppProvider;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\WhatsappTemplateIndexPropsBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('template index props preserve templates and instance projection', function () {
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $user->tenantId,
        'user_id' => $user->id,
        'name' => 'meta-instance',
        'display_name' => 'Meta Instance',
        'provider' => WhatsAppProvider::MetaCloud,
        'meta_waba_id' => 'waba-123',
        'meta_access_token' => 'token-123',
    ]);

    $approvedTemplate = WhatsappTemplate::factory()->metaHsm()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'name' => 'Template aprovado',
    ]);
    WhatsappTemplate::factory()->metaHsm()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'PENDING',
        'name' => 'Template pendente',
    ]);

    $request = Request::create('/templates', 'GET', ['status' => 'APPROVED']);

    $props = app(WhatsappTemplateIndexPropsBuilder::class)->build($request, (string) $user->tenantId);

    expect($props)->toHaveKeys(['templates', 'instances', 'currentKind', 'flash', 'error'])
        ->and($props['currentKind'])->toBe(TemplateKind::MetaHsm->value)
        ->and($props['templates']->total())->toBe(1)
        ->and($props['templates']->items()[0]->id)->toBe($approvedTemplate->id)
        ->and($props['instances']->first())->toMatchArray([
            'id' => $instance->id,
            'name' => 'meta-instance',
            'display_name' => 'Meta Instance',
            'provider' => WhatsAppProvider::MetaCloud->value,
            'meta_waba_id' => 'waba-123',
            'has_meta_access_token' => true,
        ]);
});
