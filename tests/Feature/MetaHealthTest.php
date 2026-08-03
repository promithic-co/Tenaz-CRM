<?php

use App\Enums\MetaHealthStatus;
use App\Events\InstanceHealthChanged;
use App\Jobs\SyncMetaHealthJob;
use App\Models\Campaign;
use App\Models\ContactListEntry;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\CampaignService;
use App\Services\WhatsApp\MetaHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * Build the Graph payload shape documented for
 * GET /<PHONE_NUMBER_ID>?fields=health_status.
 *
 * @param  list<array<string, mixed>>  $entities
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function metaHealthPayload(string $overall, array $entities, array $extra = []): array
{
    return array_merge([
        'id' => '106540352242922',
        'health_status' => [
            'can_send_message' => $overall,
            'entities' => $entities,
        ],
    ], $extra);
}

// ─── MetaHealthService: Graph payload → instance snapshot ─────────────────────

it('maps an AVAILABLE health payload onto the instance', function (): void {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaHealthPayload('AVAILABLE', [
            ['entity_type' => 'PHONE_NUMBER', 'id' => '1', 'can_send_message' => 'AVAILABLE'],
            ['entity_type' => 'WABA', 'id' => '2', 'can_send_message' => 'AVAILABLE'],
            ['entity_type' => 'BUSINESS', 'id' => '3', 'can_send_message' => 'AVAILABLE'],
            ['entity_type' => 'APP', 'id' => '4', 'can_send_message' => 'AVAILABLE'],
        ], [
            'quality_rating' => 'GREEN',
            'name_status' => 'APPROVED',
            'whatsapp_business_manager_messaging_limit' => 'TIER_2K',
            'throughput' => ['level' => 'STANDARD'],
            'status' => 'CONNECTED',
        ])),
    ]);

    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create();

    app(MetaHealthService::class)->refresh($instance);

    $instance->refresh();

    expect($instance->healthStatus())->toBe(MetaHealthStatus::Available)
        ->and($instance->healthReasons())->toBe([])
        ->and($instance->meta_name_status)->toBe('APPROVED')
        ->and($instance->meta_portfolio_messaging_limit)->toBe('TIER_2K')
        ->and($instance->meta_throughput_level)->toBe('STANDARD')
        ->and($instance->meta_number_status)->toBe('CONNECTED')
        ->and($instance->meta_health_checked_at)->not->toBeNull()
        ->and($instance->meta_health_entities)->toHaveCount(4);
});

it('captures additional_info as the reason when a node is LIMITED', function (): void {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaHealthPayload('LIMITED', [
            [
                'entity_type' => 'PHONE_NUMBER',
                'id' => '1',
                'can_send_message' => 'LIMITED',
                'additional_info' => ['Your display name has not been approved yet.'],
            ],
            ['entity_type' => 'WABA', 'id' => '2', 'can_send_message' => 'AVAILABLE'],
        ])),
    ]);

    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create();

    app(MetaHealthService::class)->refresh($instance);

    $instance->refresh();

    expect($instance->healthStatus())->toBe(MetaHealthStatus::Limited)
        ->and($instance->healthReasons())->toBe(['Your display name has not been approved yet.']);
});

it('captures error_description and possible_solution when a node is BLOCKED', function (): void {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaHealthPayload('BLOCKED', [
            ['entity_type' => 'PHONE_NUMBER', 'id' => '1', 'can_send_message' => 'AVAILABLE'],
            [
                'entity_type' => 'WABA',
                'id' => '2',
                'can_send_message' => 'BLOCKED',
                'errors' => [[
                    'error_code' => 131031,
                    'error_description' => 'Business account is restricted.',
                    'possible_solution' => 'Complete business verification.',
                ]],
            ],
        ])),
    ]);

    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create();

    app(MetaHealthService::class)->refresh($instance);

    $instance->refresh();

    expect($instance->healthStatus())->toBe(MetaHealthStatus::Blocked)
        ->and($instance->healthReasons())
        ->toBe(['Business account is restricted. Complete business verification.']);
});

it('ignores calling-only errors when messaging is available', function (): void {
    // Meta's own example: the aggregate reads BLOCKED because SIP is not
    // configured, while every entity can still send messages. Trusting the
    // aggregate here would report a healthy number as blocked and refuse its
    // campaigns.
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaHealthPayload('BLOCKED', [
            [
                'entity_type' => 'PHONE_NUMBER',
                'id' => '1',
                'can_send_message' => 'AVAILABLE',
                'can_receive_call_sip' => 'BLOCKED',
                'errors' => [[
                    'error_code' => 138024,
                    'error_description' => 'WhatsApp Business calling cannot use SIP because it is not enabled',
                    'possible_solution' => 'Configure SIP',
                ]],
            ],
            ['entity_type' => 'WABA', 'id' => '2', 'can_send_message' => 'AVAILABLE'],
        ])),
    ]);

    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create();

    app(MetaHealthService::class)->refresh($instance);

    $instance->refresh();

    expect($instance->healthStatus())->toBe(MetaHealthStatus::Available)
        ->and($instance->healthReasons())->toBe([]);
});

it('degrades to UNKNOWN when the Graph call fails', function (): void {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth token']], 401),
    ]);

    $instance = WhatsappInstance::factory()->metaCloud()->create();

    app(MetaHealthService::class)->refresh($instance);

    $instance->refresh();

    expect($instance->healthStatus())->toBe(MetaHealthStatus::Unknown)
        ->and($instance->meta_health_checked_at)->not->toBeNull();
});

it('does not erase a webhook-delivered quality rating when Meta returns NA', function (): void {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaHealthPayload('AVAILABLE', [
            ['entity_type' => 'WABA', 'id' => '2', 'can_send_message' => 'AVAILABLE'],
        ], ['quality_rating' => 'NA'])),
    ]);

    $instance = WhatsappInstance::factory()->metaCloud()->create(['meta_quality_rating' => 'GREEN']);

    app(MetaHealthService::class)->refresh($instance);

    expect($instance->refresh()->meta_quality_rating)->toBe('GREEN');
});

// ─── SyncMetaHealthJob ────────────────────────────────────────────────────────

it('broadcasts only when the health status actually changes', function (): void {
    Event::fake([InstanceHealthChanged::class]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(metaHealthPayload('AVAILABLE', [
            ['entity_type' => 'WABA', 'id' => '2', 'can_send_message' => 'AVAILABLE'],
        ])),
    ]);

    $instance = WhatsappInstance::factory()->metaCloud()->create();

    // Already AVAILABLE from the factory: an unchanged probe is silent.
    (new SyncMetaHealthJob($instance->id))->handle(app(MetaHealthService::class));
    Event::assertNotDispatched(InstanceHealthChanged::class);

    $instance->update(['meta_health_status' => MetaHealthStatus::Blocked]);

    (new SyncMetaHealthJob($instance->id))->handle(app(MetaHealthService::class));
    Event::assertDispatched(InstanceHealthChanged::class);
});

// ─── account_update webhook ───────────────────────────────────────────────────

/**
 * @param  array<string, mixed>  $value
 */
function postMetaWebhook(WhatsappInstance $instance, string $field, array $value): TestResponse
{
    return test()->postJson('/api/webhooks/meta', [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => $instance->meta_waba_id,
            'time' => now()->timestamp,
            'changes' => [['field' => $field, 'value' => $value]],
        ]],
    ]);
}

it('persists an account restriction instead of dropping the webhook', function (): void {
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->create();

    postMetaWebhook($instance, 'account_update', [
        'event' => 'ACCOUNT_RESTRICTION',
        'restriction_info' => [
            ['restriction_type' => 'RESTRICTED_BIZ_INITIATED_MESSAGING', 'expiration' => now()->addDay()->timestamp],
            ['restriction_type' => 'RESTRICTED_ADD_PHONE_NUMBER_ACTION', 'expiration' => now()->addDay()->timestamp],
        ],
    ])->assertNoContent();

    $instance->refresh();

    expect($instance->meta_account_restrictions['restrictions'])->toHaveCount(2)
        ->and($instance->activeMessagingRestrictions())->toBe(['RESTRICTED_BIZ_INITIATED_MESSAGING']);

    Queue::assertPushed(SyncMetaHealthJob::class);
});

it('records a WABA ban and clears it on reinstatement', function (): void {
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->create();

    postMetaWebhook($instance, 'account_update', [
        'event' => 'DISABLED_UPDATE',
        'ban_info' => ['waba_ban_state' => 'DISABLE', 'waba_ban_date' => 'April 17, 2025'],
    ])->assertNoContent();

    expect($instance->refresh()->meta_ban_state)->toBe('DISABLE');

    postMetaWebhook($instance, 'account_update', [
        'event' => 'DISABLED_UPDATE',
        'ban_info' => ['waba_ban_state' => 'REINSTATE'],
    ])->assertNoContent();

    expect($instance->refresh()->meta_ban_state)->toBeNull();
});

it('stores one live account alert per type and drops resolved ones', function (): void {
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->create();

    postMetaWebhook($instance, 'account_alerts', [
        'entity_type' => 'BUSINESS',
        'entity_id' => '506914307656634',
        'alert_info' => [
            'alert_severity' => 'WARNING',
            'alert_status' => 'ACTIVE',
            'alert_type' => 'INCREASED_CAPABILITIES_ELIGIBILITY_DEFERRED',
            'alert_description' => 'Limits cannot be increased for your business.',
        ],
    ])->assertNoContent();

    expect($instance->refresh()->meta_account_restrictions['alerts'])->toHaveCount(1);

    postMetaWebhook($instance, 'account_alerts', [
        'entity_type' => 'BUSINESS',
        'entity_id' => '506914307656634',
        'alert_info' => [
            'alert_severity' => 'WARNING',
            'alert_status' => 'RESOLVED',
            'alert_type' => 'INCREASED_CAPABILITIES_ELIGIBILITY_DEFERRED',
            'alert_description' => 'Limits cannot be increased for your business.',
        ],
    ])->assertNoContent();

    expect($instance->refresh()->meta_account_restrictions['alerts'])->toBe([]);
});

it('applies a display name decision from phone_number_name_update', function (): void {
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->create();

    postMetaWebhook($instance, 'phone_number_name_update', [
        'display_phone_number' => '15550783881',
        'decision' => 'REJECTED',
        'requested_verified_name' => 'Lucky Shrub',
        'rejection_reason' => 'NAME_FORMAT_UNACCEPTABLE',
    ])->assertNoContent();

    $instance->refresh();

    expect($instance->meta_name_status)->toBe('REJECTED')
        ->and($instance->meta_account_restrictions['name_rejection_reason'])->toBe('NAME_FORMAT_UNACCEPTABLE');
});

// ─── Portfolio messaging limit ────────────────────────────────────────────────

it('spreads the portfolio messaging limit across every number on the WABA', function (): void {
    // Since 2025-10-07 the limit belongs to the business portfolio, not the
    // number, so a sibling number on the same WABA must report the same ceiling.
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->create();
    $sibling = WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => $instance->tenant_id,
        'agent_id' => $instance->agent_id,
        'meta_waba_id' => $instance->meta_waba_id,
    ]);

    postMetaWebhook($instance, 'business_capability_update', [
        'max_daily_conversations_per_business' => 2000,
        'max_phone_numbers_per_waba' => 25,
    ])->assertNoContent();

    expect($instance->refresh()->meta_portfolio_messaging_limit)->toBe('TIER_2K')
        ->and($sibling->refresh()->meta_portfolio_messaging_limit)->toBe('TIER_2K');
});

it('reads the portfolio limit whether Meta sends an integer or a TIER constant', function (): void {
    // Meta documents the parameter as an integer but lists TIER_ values for it.
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->create();

    postMetaWebhook($instance, 'business_capability_update', [
        'max_daily_conversations_per_business' => 'TIER_100K',
    ])->assertNoContent();

    expect($instance->refresh()->meta_portfolio_messaging_limit)->toBe('TIER_100K');

    postMetaWebhook($instance, 'business_capability_update', [
        'max_daily_conversations_per_business' => -1,
    ])->assertNoContent();

    expect($instance->refresh()->meta_portfolio_messaging_limit)->toBe('TIER_UNLIMITED');
});

it('captures the portfolio limit riding along on a quality update', function (): void {
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->create();

    postMetaWebhook($instance, 'phone_number_quality_update', [
        'display_phone_number' => '15550783881',
        'event' => 'UPGRADE',
        'new_quality_score' => 'GREEN',
        'max_daily_conversations_per_business' => 10000,
    ])->assertNoContent();

    $instance->refresh();

    expect($instance->meta_portfolio_messaging_limit)->toBe('TIER_10K')
        ->and($instance->meta_quality_rating)->toBe('GREEN');
});

it('keeps the health snapshot when the Graph version rejects the portfolio field', function (): void {
    // Graph fails the whole request for an unknown field rather than omitting
    // it, which would turn every instance UNKNOWN on an older API version.
    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push([
                'error' => [
                    'message' => '(#100) Tried accessing nonexisting field (whatsapp_business_manager_messaging_limit) on node type (WhatsAppBusinessPhoneNumber)',
                    'code' => 100,
                ],
            ], 400)
            ->push(metaHealthPayload('AVAILABLE', [
                ['entity_type' => 'PHONE_NUMBER', 'id' => '1', 'can_send_message' => 'AVAILABLE'],
            ], ['messaging_limit_tier' => 'TIER_1K'])),
    ]);

    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create();

    app(MetaHealthService::class)->refresh($instance);

    $instance->refresh();

    expect($instance->healthStatus())->toBe(MetaHealthStatus::Available)
        ->and($instance->meta_portfolio_messaging_limit)->toBe('TIER_1K');

    Http::assertSentCount(2);
});

// ─── Campaign health gate ─────────────────────────────────────────────────────

function makeCampaignOnInstance(WhatsappInstance $instance): Campaign
{
    $campaign = Campaign::factory()->create([
        'status' => 'draft',
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $campaign->whatsappTemplate()->associate(
        WhatsappTemplate::factory()->create([
            'tenant_id' => $campaign->tenant_id,
            'whatsapp_instance_id' => $instance->id,
            'status' => 'APPROVED',
            'meta_template_name' => 'health_gate_template',
            'meta_waba_id' => $instance->meta_waba_id,
        ])
    );
    $campaign->save();

    ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    return $campaign;
}

it('refuses to start a campaign on a BLOCKED number', function (): void {
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->healthBlocked()->create();
    $campaign = makeCampaignOnInstance($instance);

    expect(fn () => app(CampaignService::class)->start($campaign))
        ->toThrow(RuntimeException::class, 'A Meta bloqueou o envio');

    expect($campaign->refresh()->status)->toBe('draft');
});

it('lets a campaign start on a LIMITED number', function (): void {
    // Meta still delivers on a LIMITED number, it only caps the daily volume,
    // so refusing the start would reject a send that would have worked. The
    // campaign page carries the warning instead.
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->healthLimited()->create();
    $campaign = makeCampaignOnInstance($instance);

    app(CampaignService::class)->start($campaign);

    expect($campaign->refresh()->status)->toBe('sending');
});

it('refuses to start a campaign while business-initiated messaging is restricted', function (): void {
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_account_restrictions' => [
            'restrictions' => [[
                'type' => 'RESTRICTED_BIZ_INITIATED_MESSAGING',
                'expires_at' => now()->addDay()->toIso8601String(),
            ]],
        ],
    ]);
    $campaign = makeCampaignOnInstance($instance);

    expect(fn () => app(CampaignService::class)->start($campaign))
        ->toThrow(RuntimeException::class, 'restringiu o envio');
});

it('lets a campaign start when a restriction has already expired', function (): void {
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_account_restrictions' => [
            'restrictions' => [[
                'type' => 'RESTRICTED_BIZ_INITIATED_MESSAGING',
                'expires_at' => now()->subDay()->toIso8601String(),
            ]],
        ],
    ]);
    $campaign = makeCampaignOnInstance($instance);

    app(CampaignService::class)->start($campaign);

    expect($campaign->refresh()->status)->toBe('sending');
});

it('never blocks a campaign on an UNKNOWN health probe', function (): void {
    // A Graph outage or a coexistence token without WABA visibility must not
    // take every campaign in the account offline.
    Queue::fake();
    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create();
    $campaign = makeCampaignOnInstance($instance);

    app(CampaignService::class)->start($campaign);

    expect($campaign->refresh()->status)->toBe('sending');
});

// ─── Account names ────────────────────────────────────────────────────────────

it('stores the account names behind the WABA and portfolio IDs', function (): void {
    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create();

    Http::fake(fn ($request) => str_contains($request->url(), (string) $instance->meta_waba_id)
        ? Http::response(['name' => 'Amec Promotora', 'owner_business_info' => ['name' => 'Grupo Amec']])
        : Http::response(metaHealthPayload('AVAILABLE', [
            ['entity_type' => 'PHONE_NUMBER', 'id' => '1', 'can_send_message' => 'AVAILABLE'],
        ], ['verified_name' => 'Amec Crédito Consignado'])));

    app(MetaHealthService::class)->refresh($instance);

    $instance->refresh();

    expect($instance->meta_waba_name)->toBe('Amec Promotora')
        ->and($instance->meta_business_name)->toBe('Grupo Amec')
        // verified_name was already requested from Graph and then thrown away;
        // it is the name customers actually see on the conversation.
        ->and($instance->meta_verified_name)->toBe('Amec Crédito Consignado');
});

it('only fetches the account names once', function (): void {
    // The scheduler probes every 15 minutes. A WABA rename is rare enough that
    // paying a second Graph call per instance per tick would be a bad trade.
    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create([
        'meta_waba_name' => 'Amec Promotora',
    ]);

    Http::fake(['graph.facebook.com/*' => Http::response(metaHealthPayload('AVAILABLE', []))]);

    app(MetaHealthService::class)->refresh($instance);

    Http::assertSentCount(1);
});

it('re-fetches the account names when the user presses refresh', function (): void {
    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create([
        'meta_waba_name' => 'Nome antigo',
    ]);

    Http::fake(fn ($request) => str_contains($request->url(), (string) $instance->meta_waba_id)
        ? Http::response(['name' => 'Nome novo'])
        : Http::response(metaHealthPayload('AVAILABLE', [])));

    app(MetaHealthService::class)->refresh($instance, refreshAccountNames: true);

    expect($instance->refresh()->meta_waba_name)->toBe('Nome novo');
});

it('stops retrying the account names after the WABA node refuses once', function (): void {
    // Otherwise a permanently unreadable WABA logs the same warning on every
    // 15 minute tick, forever, for every instance in that state.
    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create();

    Http::fake(fn ($request) => str_contains($request->url(), (string) $instance->meta_waba_id)
        ? Http::response(['error' => ['message' => 'Unsupported get request.']], 400)
        : Http::response(metaHealthPayload('AVAILABLE', [])));

    $health = app(MetaHealthService::class);

    $health->refresh($instance);
    $health->refresh($instance->refresh());

    // Two health probes, but only the first attempted the WABA node.
    Http::assertSentCount(3);
});

it('keeps the stored account name when the WABA node is unreachable', function (): void {
    // A coexistence token can read the phone number node without reaching the
    // WABA node. Blanking the name there would lose data over a permission gap.
    $instance = WhatsappInstance::factory()->metaCloud()->healthUnknown()->create([
        'meta_waba_name' => 'Amec Promotora',
    ]);

    Http::fake(fn ($request) => str_contains($request->url(), (string) $instance->meta_waba_id)
        ? Http::response(['error' => ['message' => 'Unsupported get request.']], 400)
        : Http::response(metaHealthPayload('AVAILABLE', [])));

    app(MetaHealthService::class)->refresh($instance, refreshAccountNames: true);

    expect($instance->refresh()->meta_waba_name)->toBe('Amec Promotora');
});

// ─── Panel props ──────────────────────────────────────────────────────────────

it('ships real health props to the WhatsApp page instead of a hardcoded state', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->healthLimited()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'phone_number' => '+55 67 9667-7889',
    ]);

    test()->actingAs($user)
        ->get('/whatsapp')
        ->assertInertia(fn ($page) => $page
            ->component('whatsapp/Index')
            ->where('instances.0.id', $instance->id)
            ->where('instances.0.health_status', 'LIMITED')
            ->where(
                'instances.0.health_reasons.0.title',
                'O nome que seus clientes veem ainda está em análise'
            )
        );
});

it('warns on the campaign page when Meta has limited the number', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->healthLimited()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'meta_portfolio_messaging_limit' => 'TIER_2K',
    ]);
    $campaign = makeCampaignOnInstance($instance);

    test()->actingAs($user)
        ->get("/campanhas/{$campaign->id}")
        ->assertInertia(fn ($page) => $page
            ->component('campanhas/Show')
            ->where('instanceHealth.status', 'LIMITED')
            ->where('instanceHealth.portfolio_messaging_limit', 'TIER_2K')
            ->where(
                'instanceHealth.reasons.0.title',
                'O nome que seus clientes veem ainda está em análise'
            )
        );
});

it('re-probes Meta from the refresh endpoint', function (): void {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaHealthPayload('BLOCKED', [
            [
                'entity_type' => 'WABA',
                'id' => '2',
                'can_send_message' => 'BLOCKED',
                'errors' => [[
                    'error_code' => 131031,
                    'error_description' => 'Business account is restricted.',
                    'possible_solution' => 'Complete business verification.',
                ]],
            ],
        ])),
    ]);

    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);

    test()->actingAs($user)
        ->postJson("/whatsapp/{$instance->id}/health")
        ->assertOk()
        ->assertJsonPath('health_status', 'BLOCKED')
        ->assertJsonPath('health_reasons.0.title', 'Sua empresa ainda não foi verificada pela Meta');
});
