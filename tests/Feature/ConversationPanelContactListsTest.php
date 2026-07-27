<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Tenant, 1: User, 2: Lead}
 */
function panelListsSetup(TenantRole $role = TenantRole::Owner): array
{
    $tenant = Tenant::create(['name' => 'PanelListsTest']);

    $actor = User::factory()->create();
    $actor->tenants()->detach();
    $actor->tenants()->attach($tenant->id, ['role' => $role->value]);

    $agent = Agent::factory()->create([
        'user_id' => $actor->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'assigned_user_id' => $actor->id,
    ]);

    return [$tenant, $actor, $lead];
}

test('panel props offer the tenant static contact lists to an owner', function (): void {
    [$tenant, $owner, $lead] = panelListsSetup();

    ContactList::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'name' => 'Aposentados INSS',
        'is_dynamic' => false,
        'entries_count' => 12,
    ]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activeConversation.contact_lists', 1)
            ->where('activeConversation.contact_lists.0.name', 'Aposentados INSS')
            ->where('activeConversation.contact_lists.0.entries_count', 12)
        );
});

/**
 * A dynamic list recomputes its membership from `filters_json`, so a hand-added
 * entry would be silently dropped on the next refresh. The panel must not offer it.
 */
test('dynamic lists are never offered as a manual destination', function (): void {
    [$tenant, $owner, $lead] = panelListsSetup();

    ContactList::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'name' => 'Lista estática',
        'is_dynamic' => false,
    ]);
    ContactList::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'name' => 'Lista dinâmica',
        'is_dynamic' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activeConversation.contact_lists', 1)
            ->where('activeConversation.contact_lists.0.name', 'Lista estática')
        );
});

test('lists from another tenant are not offered', function (): void {
    [$tenant, $owner, $lead] = panelListsSetup();

    $otherTenant = Tenant::create(['name' => 'OtherPanelLists']);
    ContactList::factory()->create([
        'tenant_id' => (string) $otherTenant->id,
        'name' => 'Lista alheia',
        'is_dynamic' => false,
    ]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activeConversation.contact_lists', 0));
});

/**
 * Filing a contact is inbox work, so a restricted member sees the lists and may
 * add to them (ContactListPolicy::addEntry) even though managing the list itself
 * stays with owners and administrators.
 */
test('a restricted member is offered the lists and can add to them', function (): void {
    [$tenant, $member, $lead] = panelListsSetup(TenantRole::User);

    $list = ContactList::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'name' => 'Aposentados INSS',
        'is_dynamic' => false,
        'entries_count' => 0,
    ]);

    $this->actingAs($member)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activeConversation.contact_lists', 1)
            ->where('activeConversation.contact_lists.0.name', 'Aposentados INSS')
        );

    $this->actingAs($member)
        ->post(route('listas-contato.entries.store', $list), [
            'phone' => $lead->whatsapp,
            'name' => $lead->nome,
        ])
        ->assertRedirect();

    expect(ContactListEntry::query()->where('contact_list_id', $list->id)->exists())->toBeTrue();
});

/**
 * The weakened ability is scoped to adding: a restricted member must still not be
 * able to rename, refilter, freeze or delete the list.
 */
test('a restricted member still cannot manage the list itself', function (): void {
    [$tenant, $member, $lead] = panelListsSetup(TenantRole::User);

    $list = ContactList::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'is_dynamic' => false,
    ]);

    $this->actingAs($member)->get(route('listas-contato.index'))->assertForbidden();
    $this->actingAs($member)->delete(route('listas-contato.destroy', $list))->assertForbidden();
    $this->actingAs($member)
        ->post(route('listas-contato.freeze', $list))
        ->assertForbidden();
});

test('a member of another tenant cannot add to the list', function (): void {
    [$tenant, $owner, $lead] = panelListsSetup();

    $list = ContactList::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'is_dynamic' => false,
    ]);

    $otherTenant = Tenant::create(['name' => 'OutsiderLists']);
    $outsider = User::factory()->create();
    $outsider->tenants()->detach();
    $outsider->tenants()->attach($otherTenant->id, ['role' => TenantRole::Owner->value]);

    $this->actingAs($outsider)
        ->post(route('listas-contato.entries.store', $list), [
            'phone' => $lead->whatsapp,
            'name' => $lead->nome,
        ])
        // The BelongsToTenant global scope hides the list at route-model binding,
        // so an outsider gets a 404 rather than a 403 that would confirm it exists.
        // ContactListPolicy::addEntry still checks the tenant, as the second latch.
        ->assertNotFound();

    expect(ContactListEntry::query()->where('contact_list_id', $list->id)->exists())->toBeFalse();
});

test('adding the conversation to a list creates the entry and bumps the count', function (): void {
    [$tenant, $owner, $lead] = panelListsSetup();

    $list = ContactList::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'is_dynamic' => false,
        'entries_count' => 0,
    ]);

    $this->actingAs($owner)
        ->from(route('conversas.show', $lead))
        ->post(route('listas-contato.entries.store', $list), [
            'phone' => $lead->whatsapp,
            'name' => $lead->nome,
        ])
        ->assertRedirect(route('conversas.show', $lead));

    expect(ContactListEntry::query()->where('contact_list_id', $list->id)->where('phone', $lead->whatsapp)->exists())
        ->toBeTrue()
        ->and($list->fresh()->entries_count)->toBe(1);
});
