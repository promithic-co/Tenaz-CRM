<?php

use App\Models\StatusMachine;
use App\Services\StatusMachineService;

it('admin can create a custom status with safe default transitions', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $this->actingAs($user)
        ->postJson('/configuracoes/pipeline/statuses', [
            'name' => 'Aguardando Documento',
            'color' => 'blue',
        ])
        ->assertCreated()
        ->assertJsonPath('status.slug', 'aguardando-documento');

    $machine = StatusMachine::where('tenant_id', $tenantId)->firstOrFail();

    expect($machine->canTransition('novo', 'aguardando-documento'))->toBeTrue()
        ->and($machine->canTransition('aguardando-documento', 'novo'))->toBeTrue()
        ->and($machine->canTransition('aguardando-documento', 'convertido'))->toBeTrue()
        ->and($machine->canTransition('optou_sair', 'aguardando-documento'))->toBeFalse()
        ->and($machine->canTransition('aguardando-documento', 'optou_sair'))->toBeFalse();
});
