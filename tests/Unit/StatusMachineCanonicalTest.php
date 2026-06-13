<?php

use App\Models\StatusMachine;

// ─── CANONICAL_SLUGS constant ────────────────────────────────────────────────

it('defines CANONICAL_SLUGS with all 7 AI-dependent slugs', function (): void {
    expect(StatusMachine::CANONICAL_SLUGS)->toBe([
        'novo',
        'qualificado',
        'sem_credito',
        'desqualificado',
        'escalado',
        'convertido',
        'optou_sair',
    ]);
});

// ─── default() includes is_canonical ─────────────────────────────────────────

it('default() statuses all have is_canonical = true', function (): void {
    $statuses = StatusMachine::default()->getStatuses();

    expect($statuses)->each(fn ($s) => $s->toHaveKey('is_canonical', true));
});

it('default() statuses all have a position field', function (): void {
    $statuses = StatusMachine::default()->getStatuses();

    $statuses->each(function (array $status, int $index): void {
        expect($status)->toHaveKey('position', $index);
    });
});

it('default() has exactly 7 canonical statuses matching CANONICAL_SLUGS', function (): void {
    $slugs = StatusMachine::default()->getStatuses()->pluck('slug')->all();

    expect($slugs)->toBe(StatusMachine::CANONICAL_SLUGS);
});

it('default() first status has is_canonical = true', function (): void {
    $first = StatusMachine::default()->getStatuses()->first();

    expect($first['is_canonical'])->toBeTrue();
});

it('default() does not lose existing keys when adding canonical fields', function (): void {
    $statuses = StatusMachine::default()->getStatuses();

    $statuses->each(function (array $status): void {
        expect($status)
            ->toHaveKey('slug')
            ->toHaveKey('label')
            ->toHaveKey('color')
            ->toHaveKey('is_terminal')
            ->toHaveKey('is_canonical')
            ->toHaveKey('position');
    });
});
