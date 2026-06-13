<?php

use App\Jobs\DispatchMetaQualityAutoPauseJob;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createTenantWithUser(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::create(['name' => $user->name.' Tenant']);
    $user->tenants()->attach($tenant->id, ['role' => 'owner']);

    return ['user' => $user, 'tenant' => $tenant];
}

it('pauses only tenant_a meta_cloud campaigns on RED quality', function (): void {
    ['user' => $userA, 'tenant' => $tenantA] = createTenantWithUser();
    ['user' => $userB, 'tenant' => $tenantB] = createTenantWithUser();

    $instA = WhatsappInstance::factory()->metaCloud()->create(['user_id' => $userA->id, 'tenant_id' => $tenantA->id]);
    $instB2 = WhatsappInstance::factory()->metaCloud()->create(['user_id' => $userA->id, 'tenant_id' => $tenantA->id]);
    $instB = WhatsappInstance::factory()->metaCloud()->create(['user_id' => $userB->id, 'tenant_id' => $tenantB->id]);

    $contactListA = ContactList::factory()->create(['tenant_id' => $tenantA->id]);
    $contactListB = ContactList::factory()->create(['tenant_id' => $tenantB->id]);

    $c1 = Campaign::factory()->create([
        'tenant_id' => $tenantA->id,
        'whatsapp_instance_id' => $instA->id,
        'status' => 'sending',
        'contact_list_id' => $contactListA->id,
    ]);
    $c2 = Campaign::factory()->create([
        'tenant_id' => $tenantA->id,
        'whatsapp_instance_id' => $instA->id,
        'status' => 'draft',
        'contact_list_id' => $contactListA->id,
    ]);
    // Campaign on a different instance — should not be paused
    $c3 = Campaign::factory()->create([
        'tenant_id' => $tenantA->id,
        'whatsapp_instance_id' => $instB2->id,
        'status' => 'sending',
        'contact_list_id' => $contactListA->id,
    ]);
    // Campaign belonging to tenant B — should not be paused
    $c4 = Campaign::factory()->create([
        'tenant_id' => $tenantB->id,
        'whatsapp_instance_id' => $instB->id,
        'status' => 'sending',
        'contact_list_id' => $contactListB->id,
    ]);

    (new DispatchMetaQualityAutoPauseJob($instA->id))->handle();

    expect($c1->fresh()->status)->toBe('paused')
        ->and($c2->fresh()->status)->toBe('paused')
        ->and($c3->fresh()->status)->toBe('sending')
        ->and($c4->fresh()->status)->toBe('sending');
});

it('template status update with RED score triggers auto pause job', function (): void {
    Queue::fake();
    config()->set('services.meta.app_secret', 'test-secret');

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '555444',
    ]);

    $payload = [
        'entry' => [[
            'id' => $instance->meta_waba_id,
            'changes' => [[
                'field' => 'message_template_status_update',
                'value' => [
                    'event' => 'QUALITY_UPDATE',
                    'message_template_name' => 'promo',
                    'new_quality_score' => 'RED',
                ],
            ]],
        ]],
    ];
    $body = json_encode($payload);
    $sig = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $body
    );

    Queue::assertPushed(DispatchMetaQualityAutoPauseJob::class);
    expect($instance->fresh()->meta_quality_rating)->toBe('RED');
});

it('phone_number_quality_update with FLAGGED updates rating to RED without pausing campaigns', function (): void {
    Queue::fake();
    config()->set('services.meta.app_secret', 'test-secret');

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '333222',
        'meta_quality_rating' => 'GREEN',
    ]);

    $payload = [
        'entry' => [[
            'id' => $instance->meta_waba_id,
            'changes' => [[
                'field' => 'phone_number_quality_update',
                'value' => [
                    'event' => 'FLAGGED',
                    'display_phone_number' => '+55123',
                    'current_limit' => 'TIER_1K',
                ],
            ]],
        ]],
    ];
    $body = json_encode($payload);
    $sig = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $body
    );

    expect($instance->fresh()->meta_quality_rating)->toBe('RED');
    Queue::assertNotPushed(DispatchMetaQualityAutoPauseJob::class);
});
