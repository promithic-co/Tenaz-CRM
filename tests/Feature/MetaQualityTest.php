<?php

use App\Jobs\DispatchMetaQualityAutoPauseJob;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\MetaQualityRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

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

    app()->call([new DispatchMetaQualityAutoPauseJob($instA->id), 'handle']);

    expect($c1->fresh()->status)->toBe('paused')
        ->and($c2->fresh()->status)->toBe('paused')
        ->and($c3->fresh()->status)->toBe('sending')
        ->and($c4->fresh()->status)->toBe('sending');
});

it('meta RED auto pause notifies every user in the tenant', function (): void {
    ['user' => $owner, 'tenant' => $tenant] = createTenantWithUser();
    $member = User::factory()->create();
    $tenant->users()->attach($member->id, ['role' => 'user']);

    ['user' => $otherUser] = createTenantWithUser();

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $owner->id,
        'tenant_id' => $tenant->id,
        'name' => 'meta-vendas',
        'display_name' => 'Meta Vendas',
    ]);

    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_instance_id' => $instance->id,
        'name' => 'Campanha RED',
    ]);

    app()->call([new DispatchMetaQualityAutoPauseJob($instance->id), 'handle']);

    $fresh = $campaign->fresh();
    expect($fresh->status)->toBe('paused')
        ->and($fresh->pause_reason_code)->toBe(MetaQualityRiskService::PAUSE_REASON_CODE)
        ->and($fresh->paused_from_status)->toBe('sending')
        ->and($owner->unreadNotifications()->count())->toBe(1)
        ->and($member->unreadNotifications()->count())->toBe(1)
        ->and($otherUser->unreadNotifications()->count())->toBe(0);

    $this->actingAs($owner)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('campanhas.show', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaign.pause_reason_code', MetaQualityRiskService::PAUSE_REASON_CODE)
            ->where('critical_notification_count', 1)
            ->where('critical_notifications.0.campaign_id', $campaign->id)
        );
});

it('user can keep a RED quality campaign paused and clear tenant notifications', function (): void {
    ['user' => $owner, 'tenant' => $tenant] = createTenantWithUser();
    $member = User::factory()->create();
    $tenant->users()->attach($member->id, ['role' => 'user']);

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $owner->id,
        'tenant_id' => $tenant->id,
    ]);
    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_instance_id' => $instance->id,
    ]);

    app()->call([new DispatchMetaQualityAutoPauseJob($instance->id), 'handle']);

    $this->actingAs($owner)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('campanhas.quality-risk.keep-paused', $campaign))
        ->assertRedirect();

    $fresh = $campaign->fresh();
    expect($fresh->status)->toBe('paused')
        ->and($fresh->risk_acknowledged_at)->not->toBeNull()
        ->and($fresh->risk_acknowledged_by)->toBe($owner->id)
        ->and($owner->unreadNotifications()->count())->toBe(0)
        ->and($member->unreadNotifications()->count())->toBe(0);
});

it('user can continue a RED quality campaign by accepting the risk', function (): void {
    Queue::fake();

    ['user' => $owner, 'tenant' => $tenant] = createTenantWithUser();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $owner->id,
        'tenant_id' => $tenant->id,
    ]);
    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_instance_id' => $instance->id,
    ]);

    app(MetaQualityRiskService::class)->pauseInstanceCampaignsForRed($instance);

    $this->actingAs($owner)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->post(route('campanhas.quality-risk.continue', $campaign))
        ->assertRedirect();

    $fresh = $campaign->fresh();
    expect($fresh->status)->toBe('sending')
        ->and($fresh->paused_at)->toBeNull()
        ->and($fresh->risk_acknowledged_at)->not->toBeNull()
        ->and($fresh->risk_acknowledged_by)->toBe($owner->id);
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

it('phone_number_quality_update with FLAGGED updates rating to RED and schedules auto pause', function (): void {
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
    Queue::assertPushed(DispatchMetaQualityAutoPauseJob::class);
});
