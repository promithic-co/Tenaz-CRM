<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * CHARACTERIZATION TEST for ConversasController (Plan B.0).
 *
 * Locks the CURRENT behaviour of conversas.index / show prop tree, restricted-user
 * visibility, and sendMessage JSON (TEXT + MEDIA branches incl. exact idempotency-key
 * strings) as the regression oracle for B.1-B.4. This test asserts CURRENT output of
 * UNMODIFIED production code — it must stay green before and after each B refactor.
 */

/**
 * Build a tenant with an owner, and attach a restricted (TenantRole::User) member.
 *
 * @return array{Tenant, User, User}
 */
function characterizationTenant(): array
{
    $tenant = Tenant::create(['name' => 'CharTenant']);

    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $restricted = User::factory()->create();
    $restricted->tenants()->detach();
    $restricted->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    return [$tenant, $owner, $restricted];
}

// ---------------------------------------------------------------------------
// 1. conversas.index / show prop tree
// ---------------------------------------------------------------------------

test('characterization: index prop tree shape is locked', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'inst-alpha',
        'display_name' => 'Alpha',
        'default_ai_mode' => Lead::AI_MODE_AUTOMATIC,
    ]);

    Lead::factory()->forAgent($agent)->create([
        'nome' => 'Lead Um',
        'whatsapp' => '5511999990001',
        'whatsapp_instance_id' => $instance->id,
        'ai_mode' => Lead::AI_MODE_ASSISTED,
    ]);

    $this->actingAs($user)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            // pagination envelope (LengthAwarePaginator through())
            ->has('leads.data')
            ->has('leads.current_page')
            ->has('leads.per_page')
            ->where('leads.per_page', 15)
            ->has('leads.total')
            ->where('leads.total', 1)
            ->has('leads.last_page')
            // per-lead card shape (exact keys the frontend depends on)
            ->has('leads.data.0', fn ($lead) => $lead
                ->where('nome', 'Lead Um')
                ->where('whatsapp', '5511999990001')
                ->where('ai_mode', Lead::AI_MODE_ASSISTED)
                ->where('effective_ai_mode', Lead::AI_MODE_ASSISTED)
                ->where('whatsapp_instance_id', $instance->id)
                ->has('id')
                ->has('status')
                ->has('followup_status')
                ->has('followup_count')
                ->has('operational_stage')
                ->has('assigned_user_id')
                ->has('assigned_user_name')
                ->has('ultima_interacao')
                ->has('pausado')
            )
            // filters envelope (defaults)
            ->where('filters.status', 'todos')
            ->where('filters.instance', '')
            ->where('filters.search', '')
            ->where('filters.ai_mode', 'todos')
            ->where('filters.stage', 'todos')
            ->where('filters.assigned', 'todos')
            ->where('filters.sort', 'last_interaction_at')
            ->where('filters.direction', 'desc')
            // instances projection: name + label only
            ->has('instances.0', fn ($inst) => $inst
                ->where('name', 'inst-alpha')
                ->has('label')
            )
            // transfer_targets present (owner is privileged)
            ->has('transfer_targets')
            ->has('transfer_targets.0', fn ($t) => $t
                ->where('type', 'user')
                ->where('id', $user->id)
                ->where('name', $user->name)
            )
            // no active conversation on index
            ->where('activeConversation', null)
        );
});

test('characterization: show prop tree exposes activeConversation shape', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'inst-show',
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'nome' => 'Ativo Lead',
        'status' => 'qualificado',
        'whatsapp_instance_id' => $instance->id,
    ]);

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->has('activeConversation', fn ($conv) => $conv
                ->has('lead', fn ($l) => $l
                    ->where('id', $lead->id)
                    ->where('nome', 'Ativo Lead')
                    ->where('status', 'qualificado')
                    ->has('agent_id')
                    ->has('whatsapp')
                    ->has('cpf')
                    ->has('idade')
                    ->has('available_transitions')
                    ->has('ai_mode')
                    ->has('effective_ai_mode')
                    ->has('operational_stage')
                    ->has('assigned_user_id')
                    ->has('assigned_user_name')
                    ->has('ai_paused_until')
                    ->has('ai_paused_reason')
                    ->has('followup_count')
                    ->has('followup_status')
                    ->has('resumo_credito')
                    ->has('tags')
                )
                ->has('mensagens')
                ->has('pausado')
                ->has('followupStatus')
                ->has('followupHistory')
                ->has('conversationWindow')
                ->has('recentEvents')
                ->has('canStartCampaign')
                ->where('canStartCampaign', true)
                ->has('active_handoff')
                ->has('handoff_state')
                ->has('handoff_actions')
                ->has('transfer_targets')
            )
        );
});

test('characterization: privileged transfer_targets present, restricted user gets empty', function () {
    [$tenant, $owner, $restricted] = characterizationTenant();

    Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id, 'is_default' => true]);

    // Owner (privileged) sees transfer_targets populated.
    $this->actingAs($owner)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transfer_targets', fn ($targets) => count($targets) >= 1)
        );

    // Restricted user gets an empty transfer_targets list.
    $this->actingAs($restricted)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transfer_targets', [])
        );
});

// ---------------------------------------------------------------------------
// 2. Restricted-user visibility (guards B.1 scopeVisibleTo extraction)
// ---------------------------------------------------------------------------

test('characterization: restricted user sees own-agent + assigned + unassigned-agentless only', function () {
    [$tenant, $owner, $restricted] = characterizationTenant();

    // (a) own-agent lead — agent owned by the restricted user
    $ownAgent = Agent::factory()->create(['user_id' => $restricted->id, 'tenant_id' => $tenant->id]);
    $ownAgentLead = Lead::factory()->forAgent($ownAgent)->create(['nome' => 'Visivel Own Agent']);

    // (b) assigned lead — owned by another agent but assigned to the restricted user
    $ownerAgent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id]);
    $assignedLead = Lead::factory()->forAgent($ownerAgent)->create([
        'nome' => 'Visivel Assigned',
        'assigned_user_id' => $restricted->id,
    ]);

    // (c) unassigned + agentless lead
    $unassignedLead = Lead::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'agent_id' => null,
        'assigned_user_id' => null,
        'nome' => 'Visivel Unassigned',
    ]);

    // NOT visible: another agent's lead, not assigned to restricted user
    $otherAgentLead = Lead::factory()->forAgent($ownerAgent)->create([
        'nome' => 'Oculto Other Agent',
        'assigned_user_id' => null,
    ]);

    $response = $this->actingAs($restricted)
        ->get(route('conversas.index'))
        ->assertOk();

    $ids = collect($response->viewData('page')['props']['leads']['data'])->pluck('id')->all();

    expect($ids)->toContain($ownAgentLead->id)
        ->toContain($assignedLead->id)
        ->toContain($unassignedLead->id)
        ->not->toContain($otherAgentLead->id);
});

test('characterization: restricted user cannot see cross-tenant leads', function () {
    [$tenant, $owner, $restricted] = characterizationTenant();

    // Visible lead in restricted user's own tenant (unassigned/agentless queue).
    $ownTenantLead = Lead::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'agent_id' => null,
        'assigned_user_id' => null,
        'nome' => 'Mesmo Tenant',
    ]);

    // Lead in a foreign tenant — must never appear (global tenant scope).
    $otherTenant = Tenant::create(['name' => 'ForeignTenant']);
    $foreignOwner = User::factory()->create();
    $foreignOwner->tenants()->detach();
    $foreignOwner->tenants()->attach($otherTenant->id, ['role' => TenantRole::Owner->value]);
    $foreignAgent = Agent::factory()->create(['user_id' => $foreignOwner->id, 'tenant_id' => $otherTenant->id]);
    $foreignLead = Lead::factory()->forAgent($foreignAgent)->create(['nome' => 'Tenant Estrangeiro']);

    $response = $this->actingAs($restricted)
        ->get(route('conversas.index'))
        ->assertOk();

    $ids = collect($response->viewData('page')['props']['leads']['data'])->pluck('id')->all();

    expect($ids)->toContain($ownTenantLead->id)
        ->not->toContain($foreignLead->id);
});

// ---------------------------------------------------------------------------
// 3. sendMessage JSON — BOTH branches (VERIFIER-CRITICAL)
// ---------------------------------------------------------------------------

/**
 * @return array{User, Lead, WhatsappInstance}
 */
function sendMessageFixture(): array
{
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'send-instance',
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'nome' => 'Send Target',
        'whatsapp' => '5511988887777',
        'whatsapp_instance_id' => $instance->id,
    ]);

    return [$user, $lead, $instance];
}

test('characterization: sendMessage TEXT branch JSON shape and outbox idempotency key', function () {
    [$user, $lead, $instance] = sendMessageFixture();

    $response = $this->actingAs($user)
        ->postJson(route('conversas.send', $lead), [
            'content' => 'Olá, tudo bem?',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'message' => [
                'id',
                'role',
                'content',
                'hora',
                'media',
                'direction',
                'sender_type',
                'channel',
                'status',
                'source',
                'interaction_id',
                'provider_message_id',
            ],
            'outbox_id',
        ])
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('message.role', 'operator')
        ->assertJsonPath('message.content', 'Olá, tudo bem?')
        ->assertJsonPath('message.direction', 'outbound')
        ->assertJsonPath('message.sender_type', 'human')
        ->assertJsonPath('message.source', 'manual')
        ->assertJsonPath('message.status', 'queued');

    $outboxId = $response->json('outbox_id');
    expect($outboxId)->not->toBeNull();

    $outbox = WhatsappOutboxMessage::findOrFail($outboxId);

    // TEXT idempotency key (controller :693): "manual:{lead}:{interactionId}:text"
    $interactionId = $outbox->interaction_id;
    expect($outbox->idempotency_key)->toBe("manual:{$lead->id}:{$interactionId}:text");
    expect($outbox->payload_json['type'])->toBe('text');
    expect($outbox->payload_json['phone'])->toBe('5511988887777');
});

test('characterization: sendMessage MEDIA branch JSON shape and outbox idempotency key', function () {
    Storage::fake('local');
    [$user, $lead, $instance] = sendMessageFixture();

    $file = UploadedFile::fake()->image('foto.jpg', 10, 10);

    $response = $this->actingAs($user)
        ->post(route('conversas.send', $lead), [
            'content' => 'legenda da foto',
            'file' => $file,
        ])
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'message' => [
                'id',
                'role',
                'content',
                'hora',
                'media',
                'direction',
                'sender_type',
                'channel',
                'status',
                'source',
                'interaction_id',
                'provider_message_id',
            ],
            'outbox_id',
        ])
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('message.role', 'operator')
        ->assertJsonPath('message.content', 'legenda da foto')
        ->assertJsonPath('message.sender_type', 'human')
        ->assertJsonPath('message.source', 'manual');

    // media descriptor is sanitized but present on the frontend message
    expect($response->json('message.media'))->not->toBeNull();

    $outboxId = $response->json('outbox_id');
    expect($outboxId)->not->toBeNull();

    $outbox = WhatsappOutboxMessage::findOrFail($outboxId);
    $interactionId = $outbox->interaction_id;

    // MEDIA idempotency key (controller :677): "manual:{lead}:{interactionId}:media"
    expect($outbox->idempotency_key)->toBe("manual:{$lead->id}:{$interactionId}:media");

    // media outbox payload shape (controller :662-683)
    expect($outbox->payload_json['type'])->toBe('media');
    expect($outbox->payload_json['media_type'])->toBe('image');
    expect($outbox->payload_json['mime_type'])->toBe('image/jpeg');
    expect($outbox->payload_json['instance_name'])->toBe('send-instance');
    expect($outbox->payload_json['caption'])->toBe('legenda da foto');
    expect($outbox->payload_json['disk'])->toBe('local');
    expect($outbox->payload_json)->toHaveKey('disk_path');

    // streamed-to-disk: file persisted at media/<2-char-prefix>/<hash>.<ext>
    Storage::disk('local')->assertExists($outbox->payload_json['disk_path']);
});

test('characterization: sendMessage returns 422 when lead has no instance', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'whatsapp' => '5511977776666',
        'whatsapp_instance_id' => null,
    ]);

    $this->actingAs($user)
        ->postJson(route('conversas.send', $lead), [
            'content' => 'sem instancia',
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Nenhuma instância de WhatsApp associada a este lead.');

    expect(WhatsappOutboxMessage::count())->toBe(0);
});

test('characterization: sendMessage uses the per-lead instance, not a global default', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    // The lead's own instance.
    $leadInstance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'lead-own-instance',
    ]);

    // A different instance in the same tenant that must NOT be used.
    WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'other-instance',
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'whatsapp' => '5511966665555',
        'whatsapp_instance_id' => $leadInstance->id,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('conversas.send', $lead), [
            'content' => 'roteamento correto',
        ])
        ->assertOk();

    $outbox = WhatsappOutboxMessage::findOrFail($response->json('outbox_id'));

    expect($outbox->payload_json['instance_id'])->toBe($leadInstance->id);
    expect($outbox->payload_json['instance_name'])->toBe('lead-own-instance');
});
