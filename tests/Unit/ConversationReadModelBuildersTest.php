<?php

use App\Enums\TenantRole;
use App\Http\Requests\InboxFilterRequest;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\ConversationInboxPropsBuilder;
use App\Services\ConversationPanelPropsBuilder;
use App\Services\ConversationTransferTargetsBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function readModelRequest(User $user, array $query = []): InboxFilterRequest
{
    $request = InboxFilterRequest::create('/conversas', 'GET', $query);
    $request->setUserResolver(fn (): User => $user);
    $request->setContainer(app());
    $request->validateResolved();

    return $request;
}

test('transfer targets are only projected for privileged users', function () {
    $tenant = Tenant::create(['name' => 'Builder Tenant']);

    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $restricted = User::factory()->create();
    $restricted->tenants()->detach();
    $restricted->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    $targets = app(ConversationTransferTargetsBuilder::class)->forTenant((string) $tenant->id, $owner);

    expect($targets)->toContain([
        'type' => 'user',
        'id' => $owner->id,
        'name' => $owner->name,
    ])->and(app(ConversationTransferTargetsBuilder::class)->forTenant((string) $tenant->id, $restricted))->toBe([]);
});

test('inbox builder preserves the index prop envelope', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'builder-instance',
        'display_name' => 'Builder Instance',
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'nome' => 'Builder Lead',
        'whatsapp_instance_id' => $instance->id,
        'ai_mode' => Lead::AI_MODE_ASSISTED,
    ]);

    $props = app(ConversationInboxPropsBuilder::class)->build(readModelRequest($user));

    expect($props)->toHaveKeys(['leads', 'filters', 'instances', 'transfer_targets', 'activeConversation'])
        ->and($props['activeConversation'])->toBeNull()
        ->and($props['filters']['sort'])->toBe('last_interaction_at')
        ->and($props['leads']->total())->toBe(1)
        ->and($props['leads']->items()[0]['id'])->toBe($lead->id)
        ->and($props['leads']->items()[0]['effective_ai_mode'])->toBe(Lead::AI_MODE_ASSISTED)
        ->and($props['instances']->first())->toMatchArray([
            'name' => 'builder-instance',
            'label' => 'Builder Instance',
        ]);
});

test('panel builder preserves active conversation prop keys', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'nome' => 'Panel Lead',
        'status' => 'qualificado',
    ]);

    $props = app(ConversationPanelPropsBuilder::class)->build($lead, $user);

    expect($props)->toHaveKeys([
        'lead',
        'mensagens',
        'pausado',
        'followupStatus',
        'followupHistory',
        'conversationWindow',
        'recentEvents',
        'canStartCampaign',
        'active_handoff',
        'handoff_state',
        'handoff_actions',
        'transfer_targets',
    ])->and($props['lead']['id'])->toBe($lead->id)
        ->and($props['lead']['nome'])->toBe('Panel Lead')
        ->and($props['lead']['status'])->toBe('qualificado')
        ->and($props['canStartCampaign'])->toBeTrue();
});
