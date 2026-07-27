<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Tenant, 1: User, 2: Lead}
 */
function notesSetup(TenantRole $role = TenantRole::Owner): array
{
    $tenant = Tenant::create(['name' => 'NotesTest']);

    $actor = User::factory()->create();
    $actor->tenants()->detach();
    $actor->tenants()->attach($tenant->id, ['role' => $role->value]);

    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create([
        'user_id' => $owner->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        // A restricted user only reaches leads assigned to them (LeadPolicy).
        'assigned_user_id' => $actor->id,
    ]);

    return [$tenant, $actor, $lead];
}

test('panel props expose the linked contact notes', function (): void {
    [$tenant, $owner, $lead] = notesSetup();

    $contact = Contact::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'phone' => $lead->whatsapp,
        'notes' => 'Prefere ser chamado de Zé. Ligar depois das 18h.',
    ]);
    $lead->update(['contact_id' => $contact->id]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where(
                'activeConversation.lead.notes',
                'Prefere ser chamado de Zé. Ligar depois das 18h.',
            )
        );
});

test('panel props report null notes when the contact has none', function (): void {
    [$tenant, $owner, $lead] = notesSetup();

    $contact = Contact::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'phone' => $lead->whatsapp,
        'notes' => null,
    ]);
    $lead->update(['contact_id' => $contact->id]);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('activeConversation.lead.notes', null));
});

test('an operator saves notes onto the linked contact', function (): void {
    [$tenant, $owner, $lead] = notesSetup();

    $contact = Contact::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'phone' => $lead->whatsapp,
    ]);
    $lead->update(['contact_id' => $contact->id]);

    $this->actingAs($owner)
        ->from(route('conversas.show', $lead))
        ->patch(route('conversas.notes.update', $lead), ['notes' => 'Cliente pediu retorno na terça.'])
        ->assertRedirect(route('conversas.show', $lead))
        ->assertSessionHas('flash', 'Notas atualizadas.');

    expect($contact->fresh()->notes)->toBe('Cliente pediu retorno na terça.');
});

/**
 * Notes are the atendente's core habit, so the route is gated on the lead policy
 * rather than the owner/administrator gate that guards `contatos.update`.
 */
test('a restricted member may write notes on a lead assigned to them', function (): void {
    [$tenant, $member, $lead] = notesSetup(TenantRole::User);

    $contact = Contact::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'phone' => $lead->whatsapp,
    ]);
    $lead->update(['contact_id' => $contact->id]);

    $this->actingAs($member)
        ->patch(route('conversas.notes.update', $lead), ['notes' => 'Confirmou os dados por telefone.'])
        ->assertRedirect();

    expect($contact->fresh()->notes)->toBe('Confirmou os dados por telefone.');
});

test('an empty textarea clears the note instead of failing validation', function (): void {
    [$tenant, $owner, $lead] = notesSetup();

    $contact = Contact::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'phone' => $lead->whatsapp,
        'notes' => 'nota antiga',
    ]);
    $lead->update(['contact_id' => $contact->id]);

    $this->actingAs($owner)
        ->patch(route('conversas.notes.update', $lead), ['notes' => '   '])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($contact->fresh()->notes)->toBeNull();
});

test('notes longer than the column allowance are rejected', function (): void {
    [$tenant, $owner, $lead] = notesSetup();

    $contact = Contact::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'phone' => $lead->whatsapp,
        'notes' => 'nota curta',
    ]);
    $lead->update(['contact_id' => $contact->id]);

    $this->actingAs($owner)
        ->patch(route('conversas.notes.update', $lead), ['notes' => str_repeat('a', 5001)])
        ->assertSessionHasErrors('notes');

    expect($contact->fresh()->notes)->toBe('nota curta');
});

test('a lead without a contact gets one on the first note', function (): void {
    [$tenant, $owner, $lead] = notesSetup();

    expect($lead->contact_id)->toBeNull();

    $this->actingAs($owner)
        ->patch(route('conversas.notes.update', $lead), ['notes' => 'Primeira nota.'])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->contact_id)->not->toBeNull()
        ->and($lead->contact->notes)->toBe('Primeira nota.');
});

test('a member of another tenant cannot write notes', function (): void {
    [$tenant, $owner, $lead] = notesSetup();

    $otherTenant = Tenant::create(['name' => 'OtherNotes']);
    $outsider = User::factory()->create();
    $outsider->tenants()->detach();
    $outsider->tenants()->attach($otherTenant->id, ['role' => TenantRole::Owner->value]);

    $this->actingAs($outsider)
        ->patch(route('conversas.notes.update', $lead), ['notes' => 'invasão'])
        // The Lead global tenant scope turns a cross-tenant id into a 404 at
        // route-model binding, before the request's own authorize() runs.
        ->assertNotFound();
});
