<?php

use App\Models\Agent;
use App\Models\AgentInteractionEvent;
use App\Models\ContactList;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeOwnerWithInstance(): array
{
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'name' => 'inbox-'.uniqid(),
    ]);

    return [$user, $agent, $instance];
}

describe('LeadManagementController@store', function () {
    test('creates a new lead with valid payload', function () {
        [$user, $agent, $instance] = makeOwnerWithInstance();

        $this->actingAs($user)
            ->post(route('conversas.store'), [
                'nome' => 'João Silva',
                'whatsapp' => '5511999998888',
                'cpf' => '12345678901',
                'evolution_instance' => $instance->name,
            ])
            ->assertRedirect();

        $lead = Lead::where('whatsapp', '5511999998888')->first();
        expect($lead)->not->toBeNull()
            ->and($lead->nome)->toBe('João Silva')
            ->and($lead->cpf)->toBe('12345678901')
            ->and((int) $lead->agent_id)->toBe((int) $agent->id)
            ->and($lead->evolution_instance)->toBe($instance->name);
    });

    test('normalizes whatsapp and cpf digits before validation', function () {
        [$user, , $instance] = makeOwnerWithInstance();

        $this->actingAs($user)
            ->post(route('conversas.store'), [
                'nome' => 'Maria',
                'whatsapp' => '+55 (11) 99999-7777',
                'cpf' => '111.222.333-44',
                'evolution_instance' => $instance->name,
            ])
            ->assertRedirect();

        $lead = Lead::where('whatsapp', '5511999997777')->first();
        expect($lead)->not->toBeNull()
            ->and($lead->cpf)->toBe('11122233344');
    });

    test('redirects to existing active lead instead of creating duplicate', function () {
        [$user, $agent, $instance] = makeOwnerWithInstance();
        $existing = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511988887777',
            'evolution_instance' => $instance->name,
        ]);

        $this->actingAs($user)
            ->post(route('conversas.store'), [
                'nome' => 'Duplicate',
                'whatsapp' => '5511988887777',
                'evolution_instance' => $instance->name,
            ])
            ->assertRedirect(route('conversas.show', $existing));

        expect(Lead::where('whatsapp', '5511988887777')->count())->toBe(1);
    });

    test('restores a soft-deleted lead and patches name', function () {
        [$user, $agent, $instance] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511977776666',
            'nome' => 'Antigo',
            'evolution_instance' => $instance->name,
        ]);
        $lead->delete();

        $this->actingAs($user)
            ->post(route('conversas.store'), [
                'nome' => 'Novo Nome',
                'whatsapp' => '5511977776666',
                'evolution_instance' => $instance->name,
            ])
            ->assertRedirect();

        $restored = Lead::where('whatsapp', '5511977776666')->first();
        expect($restored)->not->toBeNull()
            ->and($restored->id)->toBe($lead->id)
            ->and($restored->nome)->toBe('Novo Nome');
    });

    test('rejects invalid whatsapp format', function () {
        [$user, , $instance] = makeOwnerWithInstance();

        $this->actingAs($user)
            ->post(route('conversas.store'), [
                'nome' => 'X',
                'whatsapp' => '123',
                'evolution_instance' => $instance->name,
            ])
            ->assertSessionHasErrors('whatsapp');
    });

    test('rejects instance from another tenant', function () {
        [$userA] = makeOwnerWithInstance();
        [, , $instanceB] = makeOwnerWithInstance();

        $this->actingAs($userA)
            ->post(route('conversas.store'), [
                'nome' => 'Cross tenant',
                'whatsapp' => '5511966665555',
                'evolution_instance' => $instanceB->name,
            ])
            ->assertSessionHasErrors('evolution_instance');
    });
});

describe('LeadManagementController@destroy', function () {
    test('soft-deletes a lead and preserves audit row', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
        ]);

        $this->actingAs($user)
            ->delete(route('conversas.destroy', $lead))
            ->assertRedirect(route('conversas.index'));

        expect(Lead::find($lead->id))->toBeNull()
            ->and(Lead::withTrashed()->find($lead->id))->not->toBeNull()
            ->and(AgentInteractionEvent::where('lead_id', $lead->id)->where('event_type', 'lead_deleted_manual')->exists())->toBeTrue();
    });

    test('blocks cross-tenant delete', function () {
        [$userA] = makeOwnerWithInstance();
        [$userB, $agentB] = makeOwnerWithInstance();
        $leadB = Lead::factory()->create([
            'tenant_id' => $userB->tenantId,
            'agent_id' => $agentB->id,
        ]);

        $this->actingAs($userA)
            ->delete(route('conversas.destroy', $leadB))
            ->assertNotFound();
    });
});

describe('LeadManagementController@bulkAction', function () {
    test('pauses follow-up for multiple leads', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $a = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'followup_status' => 'active',
        ]);
        $b = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'followup_status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$a->id, $b->id],
                'action' => 'pause-followup',
            ])
            ->assertRedirect();

        expect($a->fresh()->followup_status)->toBe('paused')
            ->and($b->fresh()->followup_status)->toBe('paused');
    });

    test('skips leads from other tenants without failing', function () {
        [$userA, $agentA] = makeOwnerWithInstance();
        [$userB, $agentB] = makeOwnerWithInstance();
        $mine = Lead::factory()->create([
            'tenant_id' => $userA->tenantId,
            'agent_id' => $agentA->id,
            'followup_status' => 'active',
        ]);
        $theirs = Lead::factory()->create([
            'tenant_id' => $userB->tenantId,
            'agent_id' => $agentB->id,
            'followup_status' => 'active',
        ]);

        $this->actingAs($userA)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$mine->id, $theirs->id],
                'action' => 'pause-followup',
            ])
            ->assertRedirect();

        expect($mine->fresh()->followup_status)->toBe('paused')
            ->and($theirs->fresh()->followup_status)->toBe('active');
    });

    test('rejects invalid action', function () {
        [$user] = makeOwnerWithInstance();

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [1],
                'action' => 'self-destruct',
            ])
            ->assertSessionHasErrors('action');
    });

    test('pause-ai pauses automation and records a bulk-action event', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000001',
            'ai_paused_until' => null,
        ]);

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id],
                'action' => 'pause-ai',
            ])
            ->assertRedirect();

        expect($lead->fresh()->isAiPaused())->toBeTrue()
            ->and(AgentInteractionEvent::where('lead_id', $lead->id)->where('event_type', 'lead_bulk_action')->exists())->toBeTrue();
    });

    test('pause-ai skips a soft-deleted lead (lead_deleted guard)', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000002',
        ]);
        $lead->delete();

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id],
                'action' => 'pause-ai',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash', 'Ação aplicada a 0 leads. 1 ignorados.');
    });

    test('resume-ai clears the pause for an active lead', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000003',
            'ai_paused_until' => now()->addHours(5),
        ]);

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id],
                'action' => 'resume-ai',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash', 'Ação aplicada a 1 leads. 0 ignorados.');

        expect($lead->fresh()->isAiPaused())->toBeFalse();
    });

    test('resume-ai skips a soft-deleted lead (lead_deleted guard)', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000004',
        ]);
        $lead->delete();

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id],
                'action' => 'resume-ai',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash', 'Ação aplicada a 0 leads. 1 ignorados.');
    });

    test('pause-followup skips a lead that is not active (not_active guard)', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000005',
            'followup_status' => 'inactive',
        ]);

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id],
                'action' => 'pause-followup',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash', 'Ação aplicada a 0 leads. 1 ignorados.');

        expect($lead->fresh()->followup_status)->toBe('inactive');
    });

    test('resume-followup activates a paused lead', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000006',
            'followup_status' => 'paused',
        ]);

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id],
                'action' => 'resume-followup',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash', 'Ação aplicada a 1 leads. 0 ignorados.');
    });

    test('resume-followup skips a lead that is not paused (not_paused guard)', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000007',
            'followup_status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id],
                'action' => 'resume-followup',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash', 'Ação aplicada a 0 leads. 1 ignorados.');

        expect($lead->fresh()->followup_status)->toBe('active');
    });

    test('disable-followup sets follow-up inactive with no guard', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000008',
            'followup_status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id],
                'action' => 'disable-followup',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash', 'Ação aplicada a 1 leads. 0 ignorados.');

        expect($lead->fresh()->followup_status)->toBe('inactive');
    });

    test('delete soft-deletes the lead and forces manual mode', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000009',
            'followup_status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id],
                'action' => 'delete',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash', 'Ação aplicada a 1 leads. 0 ignorados.');

        $trashed = Lead::withTrashed()->find($lead->id);
        expect(Lead::find($lead->id))->toBeNull()
            ->and($trashed->followup_status)->toBe('inactive')
            ->and($trashed->ai_mode)->toBe(Lead::AI_MODE_MANUAL);
    });

    test('counts missing ids (no matching row) as skipped', function () {
        [$user, $agent] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511900000010',
            'followup_status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('conversas.bulk-action'), [
                'lead_ids' => [$lead->id, 99999999],
                'action' => 'pause-followup',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash', 'Ação aplicada a 1 leads. 1 ignorados.');
    });
});

describe('LeadManagementController@prepareCampaign', function () {
    test('creates an individual contact list for owner and redirects to campaign create', function () {
        [$user, $agent, $instance] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'evolution_instance' => $instance->name,
            'whatsapp' => '5511955554444',
            'nome' => 'Cliente',
        ]);

        $this->actingAs($user)
            ->post(route('conversas.prepare-campaign', $lead))
            ->assertRedirect();

        $list = ContactList::where('tenant_id', $user->tenantId)
            ->where('source', 'individual')
            ->first();

        expect($list)->not->toBeNull()
            ->and($list->entries()->where('lead_id', $lead->id)->exists())->toBeTrue();
    });

    test('reuses existing individual list for the same lead', function () {
        [$user, $agent, $instance] = makeOwnerWithInstance();
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'evolution_instance' => $instance->name,
        ]);

        $this->actingAs($user)->post(route('conversas.prepare-campaign', $lead))->assertRedirect();
        $this->actingAs($user)->post(route('conversas.prepare-campaign', $lead))->assertRedirect();

        expect(ContactList::where('tenant_id', $user->tenantId)->where('source', 'individual')->count())->toBe(1);
    });
});
