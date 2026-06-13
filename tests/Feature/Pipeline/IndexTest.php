<?php

use App\Models\Agent;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use App\Models\WhatsappInstance;
use Inertia\Testing\AssertableInertia;

it('returns Inertia component pipeline/Index with columns from tenant StatusMachine', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->forTenant((string) $user->tenantId)->create();
    $tag = Tag::factory()->forTenant((string) $user->tenantId)->create([
        'name' => 'Prioritario',
        'slug' => 'prioritario',
        'color' => 'green',
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'contact_id' => $contact->id,
        'whatsapp' => $contact->phone,
        'status' => 'novo',
    ]);
    $lead->attachTag($tag);

    Lead::factory()->count(2)->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->get('/pipeline')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('pipeline/Index')
            ->has('statuses')
            ->where('statuses', fn ($statuses) => collect($statuses)->pluck('slug')->doesntContain('sem_credito'))
            ->missing('transitions')
            ->has('columns')
            ->where('columns.novo.data', fn ($cards) => collect($cards)->contains(
                fn ($card) => $card['id'] === $lead->id
                    && $card['contact_id'] === $contact->id
                    && $card['whatsapp'] === $contact->phone
                    && $card['source_label'] === 'Receptivo'
                    && collect($card['tags'])->contains(fn ($cardTag) => $cardTag['slug'] === 'prioritario')
            ))
            ->missing('columns.sem_credito')
            ->where('statuses.0.slug', 'novo'));
});

it('marks only active AI leads for the Kanban indicator', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'tenant_id' => $user->tenantId,
    ]);

    $activeLead = Lead::factory()->forAgent($agent)->create([
        'ai_mode' => Lead::AI_MODE_AUTOMATIC,
        'status' => 'novo',
    ]);

    $manualLead = Lead::factory()->forAgent($agent)->create([
        'ai_mode' => Lead::AI_MODE_MANUAL,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->get('/pipeline')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.novo.data', function ($cards) use ($activeLead, $manualLead) {
                $cardsById = collect($cards)->keyBy('id');

                return $cardsById[$activeLead->id]['automation_state'] === 'active'
                    && $cardsById[$manualLead->id]['automation_state'] === 'manual';
            }));
});

it('reports per-column count from the full status group, independent of the 30-row page', function () {
    $user = User::factory()->create();

    Lead::factory()->count(42)->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->get('/pipeline')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.novo.count', 42)
            ->where('columns.novo.data', fn ($cards) => count($cards) === 30)
            ->where('columns.novo.next_cursor', fn ($cursor) => $cursor !== null));
});

it('resolves automation_state from the instance default when the lead ai_mode is null', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'tenant_id' => $user->tenantId,
    ]);
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $user->tenantId,
        'user_id' => $user->id,
        'agent_id' => $agent->id,
        'name' => 'inst-auto-'.uniqid(),
        'default_ai_mode' => Lead::AI_MODE_AUTOMATIC,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'status' => 'novo',
        'ai_mode' => null,
        'evolution_instance' => $instance->name,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $this->actingAs($user)
        ->get('/pipeline')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.novo.data', function ($cards) use ($lead) {
                return collect($cards)->keyBy('id')[$lead->id]['automation_state'] === 'active';
            }));
});

it('forces automation_state manual for an agentless lead regardless of instance default', function () {
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $user->tenantId,
        'user_id' => $user->id,
        'name' => 'inst-manual-'.uniqid(),
        'default_ai_mode' => Lead::AI_MODE_AUTOMATIC,
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
        'agent_id' => null,
        'ai_mode' => null,
        'evolution_instance' => $instance->name,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $this->actingAs($user)
        ->get('/pipeline')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.novo.data', function ($cards) use ($lead) {
                return collect($cards)->keyBy('id')[$lead->id]['automation_state'] === 'manual';
            }));
});

it('scopes board leads to authenticated user tenant', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Lead::factory()->create([
        'tenant_id' => $userB->tenantId,
        'status' => 'novo',
        'nome' => 'Foreign Lead',
    ]);

    $this->actingAs($userA)
        ->get('/pipeline')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.novo.data', fn ($cards) => collect($cards)->pluck('nome')->doesntContain('Foreign Lead')));
});

it('excludes sandbox leads by default', function () {
    $user = User::factory()->create();

    Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
        'is_sandbox' => true,
        'nome' => 'Sandbox Lead',
    ]);

    $this->actingAs($user)
        ->get('/pipeline')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.novo.data', fn ($cards) => collect($cards)->pluck('nome')->doesntContain('Sandbox Lead')));
});
