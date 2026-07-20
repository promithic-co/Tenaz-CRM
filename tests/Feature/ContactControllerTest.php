<?php

use App\Models\Agent;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ContactController CRUD', function () {
    test('owner can create a contact via store', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/contatos', [
                'name' => 'Carlos',
                'phone' => '5511988887777',
                'cpf' => '12345678901',
                'email' => 'carlos@example.com',
            ])
            ->assertRedirect();

        $contact = Contact::where('phone', '5511988887777')->first();
        expect($contact)->not->toBeNull()
            ->and((string) $contact->tenant_id)->toBe((string) $user->tenantId)
            ->and($contact->name)->toBe('Carlos')
            ->and($contact->cpf)->toBe('12345678901');
    });

    test('tenant isolation: contact from another tenant is not visible', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->forTenant((string) $userA->tenantId)->create();

        $this->actingAs($userB)
            ->get('/contatos/'.$contact->id)
            ->assertNotFound();
    });

    test('store rejects duplicate phone per tenant', function () {
        $user = User::factory()->create();
        Contact::factory()->forTenant((string) $user->tenantId)->create([
            'phone' => '5511999990000',
        ]);

        $this->actingAs($user)
            ->post('/contatos', [
                'phone' => '5511999990000',
            ])
            ->assertSessionHasErrors('phone');
    });

    test('addToList creates entries with contact_id and skips duplicates', function () {
        $user = User::factory()->create();
        $list = ContactList::factory()->create([
            'tenant_id' => $user->tenantId,
        ]);
        $c1 = Contact::factory()->forTenant((string) $user->tenantId)->create();
        $c2 = Contact::factory()->forTenant((string) $user->tenantId)->create();

        // Pre-existing entry for c1 (no contact_id) to test that addToList links it.
        ContactListEntry::create([
            'contact_list_id' => $list->id,
            'phone' => $c1->phone,
            'opt_in_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post('/listas-contato/'.$list->id.'/contatos', [
                'contact_ids' => [$c1->id, $c2->id],
            ])
            ->assertRedirect();

        // c1 already in list → existing entry is linked, no new row.
        $c1Entries = ContactListEntry::where('contact_list_id', $list->id)
            ->where('phone', $c1->phone)
            ->get();
        expect($c1Entries)->toHaveCount(1)
            ->and((int) $c1Entries->first()->contact_id)->toBe((int) $c1->id);

        // c2 → new entry with contact_id.
        $c2Entry = ContactListEntry::where('contact_list_id', $list->id)
            ->where('phone', $c2->phone)
            ->first();
        expect($c2Entry)->not->toBeNull()
            ->and((int) $c2Entry->contact_id)->toBe((int) $c2->id);

        expect($list->fresh()->entries_count)->toBe(2);
    });

    test('search endpoint returns matches and flags already-in-list contacts', function () {
        $user = User::factory()->create();
        $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

        $c = Contact::factory()->forTenant((string) $user->tenantId)->create([
            'name' => 'Joao da Silva',
        ]);
        ContactListEntry::create([
            'contact_list_id' => $list->id,
            'contact_id' => $c->id,
            'phone' => $c->phone,
            'opt_in_status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/contatos/search?q=Joao&list_id='.$list->id);

        $response->assertOk();
        expect($response->json('already_in_list'))->toContain($c->id);
    });

    test('update persists free-text notes on the contact', function () {
        $user = User::factory()->create();
        $contact = Contact::factory()->forTenant((string) $user->tenantId)->create();

        $this->actingAs($user)
            ->patch('/contatos/'.$contact->id, [
                'phone' => $contact->phone,
                'notes' => "Cliente prefere contato à tarde.\nJá negociou em 2025.",
            ])
            ->assertRedirect();

        expect($contact->fresh()->notes)->toBe("Cliente prefere contato à tarde.\nJá negociou em 2025.");
    });

    test('show projects collected information independently from other extra data', function () {
        $user = User::factory()->create();
        $contact = Contact::factory()->forTenant((string) $user->tenantId)->create([
            'extra_data' => [
                'campaign_code' => 'summer-26',
                'collected_information' => [
                    'objetivo' => [
                        'label' => 'Objetivo',
                        'value' => 'Refinanciamento',
                        'source' => 'manual',
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('contatos.show', $contact))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('collectedInformation.0.key', 'objetivo')
                ->where('collectedInformation.0.label', 'Objetivo')
                ->where('collectedInformation.0.value', 'Refinanciamento')
                ->where('contact.extra_data.campaign_code', 'summer-26')
            );
    });

    test('show exposes follow-up state for the latest lead and per-lead in the list', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        $contact = Contact::factory()->forTenant($tenantId)->create();

        $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenantId]);

        $older = Lead::factory()->create([
            'contact_id' => $contact->id,
            'tenant_id' => $tenantId,
            'agent_id' => $agent->id,
            'followup_status' => 'inactive',
            'followup_count' => 2,
            'updated_at' => now()->subDay(),
        ]);
        $latest = Lead::factory()->create([
            'contact_id' => $contact->id,
            'tenant_id' => $tenantId,
            'agent_id' => $agent->id,
            'followup_status' => 'paused',
            'followup_count' => 1,
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('contatos.show', $contact))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('followupState.status', 'paused')
                ->where('followupState.count', 1)
                ->where('followupState.reason_label', 'pausado')
                ->where('leads.0.id', $latest->id)
                ->where('leads.0.followup.status', 'paused')
                ->where('leads.1.id', $older->id)
                ->where('leads.1.followup.status', 'inactive')
                ->where('leads.1.followup.count', 2)
            );
    });

    test('generic contact updates preserve the reserved collected information namespace', function () {
        $user = User::factory()->create();
        $contact = Contact::factory()->forTenant((string) $user->tenantId)->create([
            'extra_data' => [
                'collected_information' => [
                    'objetivo' => [
                        'label' => 'Objetivo',
                        'value' => 'Refinanciamento',
                        'source' => 'manual',
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->patch(route('contatos.update', $contact), [
                'name' => $contact->name,
                'phone' => $contact->phone,
                'extra_data' => ['campaign_code' => 'summer-26'],
            ])
            ->assertRedirect();

        expect($contact->fresh()->extra_data)
            ->campaign_code->toBe('summer-26')
            ->collected_information->toHaveKey('objetivo');
    });

    test('destroy soft-deletes the contact', function () {
        $user = User::factory()->create();
        $contact = Contact::factory()->forTenant((string) $user->tenantId)->create();

        $this->actingAs($user)
            ->delete('/contatos/'.$contact->id)
            ->assertRedirect();

        expect(Contact::withTrashed()->find($contact->id)->trashed())->toBeTrue();
    });
});
