<?php

use App\Exceptions\StatusMachine\CanonicalStatusModificationException;
use App\Exceptions\StatusMachine\DuplicateSlugException;
use App\Exceptions\StatusMachine\ProtectedTransitionException;
use App\Exceptions\StatusMachine\StatusInUseException;
use App\Models\Lead;
use App\Models\StatusMachine;
use App\Services\StatusMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makePersistedMachine(string $tenantId = 'tenant-test'): StatusMachine
{
    $default = StatusMachine::default();

    return StatusMachine::create([
        'tenant_id' => $tenantId,
        'entity_type' => 'lead',
        'statuses' => $default->statuses,
        'transitions' => $default->transitions,
        'initial_status' => $default->initial_status,
    ]);
}

function makeService(): StatusMachineService
{
    return new StatusMachineService;
}

// ─── getOrCreateForTenant ─────────────────────────────────────────────────────

it('getOrCreateForTenant creates a machine when none exists', function (): void {
    $machine = makeService()->getOrCreateForTenant('new-tenant');

    expect($machine)->toBeInstanceOf(StatusMachine::class)
        ->and($machine->exists)->toBeTrue()
        ->and($machine->tenant_id)->toBe('new-tenant');
});

it('getOrCreateForTenant returns existing machine without duplicating', function (): void {
    makePersistedMachine('existing-tenant');
    $machine = makeService()->getOrCreateForTenant('existing-tenant');

    expect(StatusMachine::where('tenant_id', 'existing-tenant')->count())->toBe(1)
        ->and($machine->tenant_id)->toBe('existing-tenant');
});

// ─── updateStatus ─────────────────────────────────────────────────────────────

it('updateStatus changes label on a canonical status', function (): void {
    $machine = makePersistedMachine();

    makeService()->updateStatus($machine, 'qualificado', ['label' => 'Lead Quente']);

    $machine->refresh();
    $status = $machine->getStatuses()->firstWhere('slug', 'qualificado');
    expect($status['label'])->toBe('Lead Quente')
        ->and($status['slug'])->toBe('qualificado');
});

it('updateStatus changes color on a canonical status', function (): void {
    $machine = makePersistedMachine();

    makeService()->updateStatus($machine, 'novo', ['color' => 'blue']);

    $machine->refresh();
    $status = $machine->getStatuses()->firstWhere('slug', 'novo');
    expect($status['color'])->toBe('blue');
});

it('updateStatus throws CanonicalStatusModificationException when changing slug', function (): void {
    $machine = makePersistedMachine();

    expect(fn () => makeService()->updateStatus($machine, 'qualificado', ['slug' => 'qualificado_novo']))
        ->toThrow(CanonicalStatusModificationException::class);
});

it('updateStatus throws InvalidArgumentException for unknown slug', function (): void {
    $machine = makePersistedMachine();

    expect(fn () => makeService()->updateStatus($machine, 'inexistente', ['label' => 'X']))
        ->toThrow(InvalidArgumentException::class);
});

// ─── addCustomStatus ──────────────────────────────────────────────────────────

it('addCustomStatus appends a new custom status with derived slug', function (): void {
    $machine = makePersistedMachine();

    $result = makeService()->addCustomStatus($machine, ['name' => 'Aguardando Documento']);

    expect($result['slug'])->toBe('aguardando-documento')
        ->and($result['is_canonical'])->toBeFalse()
        ->and($result['position'])->toBe(7); // default has 7 statuses (0-6)
});

it('addCustomStatus throws DuplicateSlugException when slug already exists', function (): void {
    $machine = makePersistedMachine();

    expect(fn () => makeService()->addCustomStatus($machine, ['name' => 'Novo']))
        ->toThrow(DuplicateSlugException::class);
});

it('addCustomStatus does not mark new status as canonical', function (): void {
    $machine = makePersistedMachine();

    $result = makeService()->addCustomStatus($machine, ['name' => 'Status Customizado']);

    expect($result['is_canonical'])->toBeFalse();
});

it('addCustomStatus creates safe default transitions for the custom status', function (): void {
    $machine = makePersistedMachine();

    makeService()->addCustomStatus($machine, ['name' => 'Aguardando Documento']);

    $machine->refresh();
    expect($machine->canTransition('novo', 'aguardando-documento'))->toBeTrue()
        ->and($machine->canTransition('qualificado', 'aguardando-documento'))->toBeTrue()
        ->and($machine->canTransition('aguardando-documento', 'novo'))->toBeTrue()
        ->and($machine->canTransition('aguardando-documento', 'qualificado'))->toBeTrue()
        ->and($machine->canTransition('aguardando-documento', 'convertido'))->toBeTrue();
});

it('addCustomStatus does not create automatic transitions involving optou_sair', function (): void {
    $machine = makePersistedMachine();

    makeService()->addCustomStatus($machine, ['name' => 'Aguardando Documento']);

    $machine->refresh();
    expect($machine->canTransition('optou_sair', 'aguardando-documento'))->toBeFalse()
        ->and($machine->canTransition('aguardando-documento', 'optou_sair'))->toBeFalse();
});

// ─── deleteCustomStatus ───────────────────────────────────────────────────────

it('deleteCustomStatus removes a custom status when no leads reference it', function (): void {
    $machine = makePersistedMachine('tenant-del');
    makeService()->addCustomStatus($machine, ['name' => 'Aguardando Doc']);
    $machine->refresh();

    makeService()->deleteCustomStatus($machine, 'aguardando-doc');

    $machine->refresh();
    $slugs = $machine->getStatuses()->pluck('slug')->all();
    expect($slugs)->not->toContain('aguardando-doc');
});

it('deleteCustomStatus throws CanonicalStatusModificationException for canonical slug', function (): void {
    $machine = makePersistedMachine();

    expect(fn () => makeService()->deleteCustomStatus($machine, 'novo'))
        ->toThrow(CanonicalStatusModificationException::class);
});

it('deleteCustomStatus throws StatusInUseException when leads are in that status', function (): void {
    $machine = makePersistedMachine('tenant-busy');
    makeService()->addCustomStatus($machine, ['name' => 'Em Análise']);
    $machine->refresh();

    Lead::factory()->create([
        'tenant_id' => 'tenant-busy',
        'status' => 'em-analise',
    ]);

    expect(fn () => makeService()->deleteCustomStatus($machine, 'em-analise'))
        ->toThrow(StatusInUseException::class);
});

// ─── addTransition ────────────────────────────────────────────────────────────

it('addTransition adds a new valid transition', function (): void {
    $machine = makePersistedMachine();

    makeService()->addTransition($machine, 'convertido', 'novo');

    $machine->refresh();
    expect($machine->canTransition('convertido', 'novo'))->toBeTrue();
});

it('addTransition throws when either slug does not exist', function (): void {
    $machine = makePersistedMachine();

    expect(fn () => makeService()->addTransition($machine, 'inexistente', 'novo'))
        ->toThrow(InvalidArgumentException::class);
});

it('addTransition throws when transition already exists', function (): void {
    $machine = makePersistedMachine();

    expect(fn () => makeService()->addTransition($machine, 'novo', 'qualificado'))
        ->toThrow(InvalidArgumentException::class);
});

// ─── removeTransition ─────────────────────────────────────────────────────────

it('removeTransition removes a non-canonical transition', function (): void {
    $machine = makePersistedMachine();
    makeService()->addTransition($machine, 'convertido', 'novo');
    $machine->refresh();

    makeService()->removeTransition($machine, 'convertido', 'novo');

    $machine->refresh();
    expect($machine->canTransition('convertido', 'novo'))->toBeFalse();
});

it('removeTransition throws ProtectedTransitionException for novo→qualificado', function (): void {
    $machine = makePersistedMachine();

    expect(fn () => makeService()->removeTransition($machine, 'novo', 'qualificado'))
        ->toThrow(ProtectedTransitionException::class);
});

it('removeTransition throws ProtectedTransitionException for qualificado→convertido', function (): void {
    $machine = makePersistedMachine();

    expect(fn () => makeService()->removeTransition($machine, 'qualificado', 'convertido'))
        ->toThrow(ProtectedTransitionException::class);
});

// ─── reorder ─────────────────────────────────────────────────────────────────

it('reorder updates positions according to the given slug order', function (): void {
    $machine = makePersistedMachine();
    $slugsInOrder = ['qualificado', 'novo', 'sem_credito', 'desqualificado', 'escalado', 'convertido', 'optou_sair'];

    makeService()->reorder($machine, $slugsInOrder);

    $machine->refresh();
    $bySlug = $machine->getStatuses()->keyBy('slug');
    expect($bySlug['qualificado']['position'])->toBe(0)
        ->and($bySlug['novo']['position'])->toBe(1);
});

it('reorder throws when slug list does not match existing statuses', function (): void {
    $machine = makePersistedMachine();

    expect(fn () => makeService()->reorder($machine, ['novo', 'qualificado']))
        ->toThrow(InvalidArgumentException::class);
});

// ─── resetToDefault ───────────────────────────────────────────────────────────

it('resetToDefault restores machine to canonical defaults', function (): void {
    $machine = makePersistedMachine();
    makeService()->addCustomStatus($machine, ['name' => 'Custom Status']);
    $machine->refresh();

    makeService()->resetToDefault($machine);

    $machine->refresh();
    $slugs = $machine->getStatuses()->pluck('slug')->all();
    expect($slugs)->toBe(StatusMachine::CANONICAL_SLUGS)
        ->and($machine->getStatuses()->count())->toBe(7);
});

it('resetToDefault throws StatusInUseException when leads use a custom status', function (): void {
    $machine = makePersistedMachine('tenant-reset-blocked');
    makeService()->addCustomStatus($machine, ['name' => 'Custom Status']);
    $machine->refresh();

    Lead::factory()->create([
        'tenant_id' => 'tenant-reset-blocked',
        'status' => 'custom-status',
    ]);

    expect(fn () => makeService()->resetToDefault($machine))
        ->toThrow(StatusInUseException::class);
});

// ─── isProtectedTransition ────────────────────────────────────────────────────

it('isProtectedTransition returns true for canonical AI transitions', function (): void {
    $service = makeService();

    expect($service->isProtectedTransition('novo', 'qualificado'))->toBeTrue()
        ->and($service->isProtectedTransition('qualificado', 'escalado'))->toBeTrue()
        ->and($service->isProtectedTransition('escalado', 'convertido'))->toBeTrue();
});

it('isProtectedTransition returns false for non-canonical transitions', function (): void {
    $service = makeService();

    expect($service->isProtectedTransition('convertido', 'novo'))->toBeFalse()
        ->and($service->isProtectedTransition('custom_a', 'custom_b'))->toBeFalse();
});
