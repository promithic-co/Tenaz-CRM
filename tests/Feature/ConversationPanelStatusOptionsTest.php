<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\StatusMachine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StatusMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * The panel's status picker is a manual control, so it offers every status in the
 * tenant pipeline rather than the outgoing edges of the transition graph. The graph
 * stays in force for automation — see LeadStatusControllerTest.
 *
 * @return array{0: Tenant, 1: User, 2: Lead}
 */
function statusOptionsSetup(string $leadStatus): array
{
    $tenant = Tenant::create(['name' => 'StatusOptionsTest']);

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
        'status' => $leadStatus,
    ]);

    return [$tenant, $owner, $lead];
}

test('the panel offers every status except the current one', function (): void {
    [, $owner, $lead] = statusOptionsSetup('novo');

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.lead.available_transitions', [
                'qualificado',
                'sem_credito',
                'desqualificado',
                'escalado',
                'convertido',
                'optou_sair',
            ])
        );
});

test('a lead in a terminal status is still offered a way back', function (string $terminal): void {
    // The regression: these statuses have no outgoing edges, so the picker was empty.
    [, $owner, $lead] = statusOptionsSetup($terminal);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.lead.available_transitions', fn (Collection $slugs): bool => $slugs->contains('novo')
                && $slugs->contains('qualificado')
                && ! $slugs->contains($terminal))
        );
})->with(['optou_sair', 'convertido']);

test('a custom status added by the tenant shows up in the picker', function (): void {
    [$tenant, $owner, $lead] = statusOptionsSetup('novo');

    $machine = app(StatusMachineService::class)->getOrCreateForTenant((string) $tenant->id);
    app(StatusMachineService::class)->addCustomStatus($machine, ['name' => 'Aguardando Doc']);

    $this->actingAs($owner)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where(
                'activeConversation.lead.available_transitions',
                fn (Collection $slugs): bool => $slugs->contains('aguardando-doc'),
            )
        );
});

test('getManualTransitions ignores the graph while getAvailableTransitions honours it', function (): void {
    $machine = StatusMachine::default();

    expect($machine->getAvailableTransitions('optou_sair'))->toBe([])
        ->and($machine->getManualTransitions('optou_sair'))->toBe([
            'novo',
            'qualificado',
            'sem_credito',
            'desqualificado',
            'escalado',
            'convertido',
        ]);
});
