<?php

use App\Models\Agent;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\ContactSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ContactSyncService', function () {
    test('syncFromLead creates a canonical contact and links lead.contact_id', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);
        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511988880000',
            'nome' => 'Ana',
            'cpf' => '11122233344',
        ]);

        $service = app(ContactSyncService::class);
        $contact = $service->syncFromLead($lead);

        expect($contact)->not->toBeNull()
            ->and((string) $contact->tenant_id)->toBe((string) $user->tenantId)
            ->and($contact->phone)->toBe('5511988880000')
            ->and($contact->name)->toBe('Ana');

        $lead->refresh();
        expect((int) $lead->contact_id)->toBe((int) $contact->id);
    });

    test('syncFromLead reuses the canonical contact for an existing tenant+phone', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenantId]);

        $service = app(ContactSyncService::class);

        // A canonical contact for this tenant+phone already exists (seeded here via
        // a contact-list entry). syncFromLead must resolve to that same contact
        // rather than creating a duplicate — dedup is keyed on tenant_id + phone,
        // independent of the originating source (entry vs lead).
        $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
        $entry = ContactListEntry::create([
            'contact_list_id' => $list->id,
            'phone' => '5511977770000',
            'name' => 'Ana',
            'opt_in_status' => 'opted_in',
            'opt_in_at' => now(),
        ]);
        $c1 = $service->syncFromEntry($entry);

        $lead = Lead::factory()->create([
            'tenant_id' => $user->tenantId,
            'agent_id' => $agent->id,
            'whatsapp' => '5511977770000',
            'nome' => 'Bruno',
        ]);
        $c2 = $service->syncFromLead($lead);

        expect((int) $c1->id)->toBe((int) $c2->id)
            ->and(Contact::withoutGlobalScopes()->where('tenant_id', (string) $user->tenantId)->where('phone', '5511977770000')->count())->toBe(1);

        $lead->refresh();
        expect((int) $lead->contact_id)->toBe((int) $c1->id);
    });

    test('syncFromEntry links contact_id to canonical contact', function () {
        $user = User::factory()->create();
        $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
        $entry = ContactListEntry::create([
            'contact_list_id' => $list->id,
            'phone' => '5511966660000',
            'name' => 'Carla',
            'opt_in_status' => 'opted_in',
            'opt_in_at' => now(),
        ]);

        $service = app(ContactSyncService::class);
        $contact = $service->syncFromEntry($entry);

        expect($contact)->not->toBeNull()
            ->and($contact->phone)->toBe('5511966660000');

        $entry->refresh();
        expect((int) $entry->contact_id)->toBe((int) $contact->id);
    });

    test('LeadManagementController@store auto-syncs a contact', function () {
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

        $this->actingAs($user)
            ->post('/conversas', [
                'nome' => 'Diana',
                'whatsapp' => '5511955554444',
                'evolution_instance' => $instance->name,
            ])
            ->assertRedirect();

        $lead = Lead::where('whatsapp', '5511955554444')->first();
        expect($lead->contact_id)->not->toBeNull();

        $contact = Contact::withoutGlobalScopes()->find($lead->contact_id);
        expect($contact->name)->toBe('Diana')
            ->and($contact->phone)->toBe('5511955554444');
    });
});
