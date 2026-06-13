<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\FollowupMessage;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;

/**
 * Creates a user with their own tenant attached (owner role).
 *
 * @return array{0: User, 1: Tenant}
 */
function createUserWithTenant(string $name = 'User'): array
{
    $user = User::factory()->create(['name' => $name]);
    $tenant = $user->tenants()->first();

    return [$user, $tenant];
}

it('lead is invisible to another tenant', function () {
    [$userA, $tenantA] = createUserWithTenant('A');
    [$userB, $tenantB] = createUserWithTenant('B');

    $agentA = Agent::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id]);
    Lead::factory()->create(['tenant_id' => $tenantA->id, 'agent_id' => $agentA->id]);

    $this->actingAs($userB);
    expect(Lead::query()->count())->toBe(0);
});

it('agent is invisible to another tenant', function () {
    [$userA, $tenantA] = createUserWithTenant('A');
    [$userB, $tenantB] = createUserWithTenant('B');

    Agent::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id]);

    $this->actingAs($userB);
    expect(Agent::query()->count())->toBe(0);
});

it('campaign is isolated per tenant', function () {
    [$userA, $tenantA] = createUserWithTenant('A');
    [$userB, $tenantB] = createUserWithTenant('B');

    $instanceA = WhatsappInstance::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id]);
    $listA = ContactList::factory()->create(['tenant_id' => $tenantA->id]);
    $templateA = WhatsappTemplate::factory()->create(['tenant_id' => $tenantA->id, 'whatsapp_instance_id' => $instanceA->id]);

    Campaign::factory()->create([
        'tenant_id' => $tenantA->id,
        'whatsapp_instance_id' => $instanceA->id,
        'contact_list_id' => $listA->id,
        'whatsapp_template_id' => $templateA->id,
    ]);

    $this->actingAs($userB);
    expect(Campaign::query()->count())->toBe(0);
});

it('contact list is isolated per tenant', function () {
    [$userA, $tenantA] = createUserWithTenant('A');
    [$userB, $tenantB] = createUserWithTenant('B');

    ContactList::factory()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($userB);
    expect(ContactList::query()->count())->toBe(0);
});

it('whatsapp template is isolated per tenant', function () {
    [$userA, $tenantA] = createUserWithTenant('A');
    [$userB, $tenantB] = createUserWithTenant('B');

    $instanceA = WhatsappInstance::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id]);
    WhatsappTemplate::factory()->create(['tenant_id' => $tenantA->id, 'whatsapp_instance_id' => $instanceA->id]);

    $this->actingAs($userB);
    expect(WhatsappTemplate::query()->count())->toBe(0);
});

it('followup message is isolated per tenant', function () {
    [$userA, $tenantA] = createUserWithTenant('A');
    [$userB, $tenantB] = createUserWithTenant('B');

    $agentA = Agent::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id]);
    $leadA = Lead::factory()->create(['tenant_id' => $tenantA->id, 'agent_id' => $agentA->id]);
    FollowupMessage::factory()->create(['tenant_id' => $tenantA->id, 'lead_id' => $leadA->id]);

    $this->actingAs($userB);
    expect(FollowupMessage::query()->count())->toBe(0);
});

it('agent config is isolated per tenant', function () {
    [$userA, $tenantA] = createUserWithTenant('A');
    [$userB, $tenantB] = createUserWithTenant('B');

    $agentA = Agent::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id]);
    AgentConfig::factory()->create(['agent_id' => $agentA->id, 'tenant_id' => $tenantA->id]);

    $this->actingAs($userB);
    expect(AgentConfig::query()->count())->toBe(0);
});

it('whatsapp instance is isolated per tenant', function () {
    [$userA, $tenantA] = createUserWithTenant('A');
    [$userB, $tenantB] = createUserWithTenant('B');

    WhatsappInstance::factory()->create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id]);

    $this->actingAs($userB);
    expect(WhatsappInstance::query()->count())->toBe(0);
});

it('CreateNewUser action creates user with a tenant attached as owner', function () {
    $action = new \App\Actions\Fortify\CreateNewUser;

    $user = $action->create([
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect($user)->not->toBeNull();
    expect($user->tenants()->count())->toBe(1);
    expect($user->tenants()->first()->pivot->role)->toBe(TenantRole::Owner->value);
});

it('user tenantId returns null instead of user id when no tenant exists', function () {
    $user = User::factory()->create();
    $user->tenants()->detach();

    expect($user->tenantId)->toBeNull();
    expect($user->tenantId)->not->toBe((string) $user->id);
});
