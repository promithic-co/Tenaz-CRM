<?php

namespace App\Services;

use App\Exceptions\StatusMachine\CanonicalStatusModificationException;
use App\Exceptions\StatusMachine\DuplicateSlugException;
use App\Exceptions\StatusMachine\ProtectedTransitionException;
use App\Exceptions\StatusMachine\StatusInUseException;
use App\Models\Lead;
use App\Models\StatusMachine;
use Illuminate\Support\Str;

/**
 * Centralises all mutations to a tenant's StatusMachine.
 *
 * All validation (canonical protection, slug uniqueness, lead-in-use checks)
 * lives here — controllers MUST call this service rather than mutating the
 * model directly. This ensures AI tools are never broken by UI changes.
 */
class StatusMachineService
{
    /**
     * Canonical transitions that the AI depends on — these cannot be removed.
     *
     * @var list<array{from: string, to: string}>
     */
    protected const PROTECTED_TRANSITIONS = [
        ['from' => 'novo', 'to' => 'qualificado'],
        ['from' => 'novo', 'to' => 'sem_credito'],
        ['from' => 'novo', 'to' => 'desqualificado'],
        ['from' => 'novo', 'to' => 'optou_sair'],
        ['from' => 'qualificado', 'to' => 'escalado'],
        ['from' => 'qualificado', 'to' => 'optou_sair'],
        ['from' => 'qualificado', 'to' => 'convertido'],
        ['from' => 'sem_credito', 'to' => 'qualificado'],
        ['from' => 'sem_credito', 'to' => 'optou_sair'],
        ['from' => 'desqualificado', 'to' => 'qualificado'],
        ['from' => 'desqualificado', 'to' => 'optou_sair'],
        ['from' => 'escalado', 'to' => 'convertido'],
        ['from' => 'escalado', 'to' => 'optou_sair'],
    ];

    /**
     * Get or create the StatusMachine for the given tenant.
     *
     * When no persisted record exists the default machine is cloned and
     * saved so the tenant has their own editable copy.
     */
    public function getOrCreateForTenant(string $tenantId): StatusMachine
    {
        $existing = StatusMachine::where('tenant_id', $tenantId)->first();

        if ($existing) {
            return $existing;
        }

        $default = StatusMachine::default();
        $machine = new StatusMachine([
            'tenant_id' => $tenantId,
            'entity_type' => 'lead',
            'statuses' => $default->statuses,
            'transitions' => $default->transitions,
            'initial_status' => $default->initial_status,
        ]);
        $machine->save();

        return $machine;
    }

    /**
     * Update the label, color, position, or is_terminal of a single status.
     *
     * The slug and is_canonical flag are always immutable — passing a new slug
     * for a canonical status throws; for custom statuses a slug change would
     * break existing lead data so it is also rejected.
     *
     * @param  array{label?: string, color?: string, position?: int, is_terminal?: bool}  $attrs
     *
     * @throws CanonicalStatusModificationException when attempting to change slug of a canonical status
     */
    public function updateStatus(StatusMachine $machine, string $slug, array $attrs): void
    {
        $statuses = $machine->getStatuses()->all();
        $found = false;

        foreach ($statuses as &$status) {
            if ($status['slug'] !== $slug) {
                continue;
            }

            $found = true;

            // Slug is always immutable (canonical or not)
            if (isset($attrs['slug']) && $attrs['slug'] !== $slug) {
                throw new CanonicalStatusModificationException($slug);
            }

            // is_canonical is always immutable
            unset($attrs['slug'], $attrs['is_canonical']);

            foreach ($attrs as $key => $value) {
                $status[$key] = $value;
            }

            break;
        }
        unset($status);

        if (! $found) {
            throw new \InvalidArgumentException("Status '{$slug}' não encontrado no pipeline.");
        }

        $machine->statuses = array_values($statuses);
        $machine->save();
    }

    /**
     * Add a new custom status to the machine.
     *
     * The slug is auto-derived from the name using Str::slug(). If the derived
     * slug already exists (canonical or custom) a DuplicateSlugException is thrown.
     *
     * @param  array{name: string, color?: string, is_terminal?: bool}  $attrs
     * @return array{slug: string, label: string, color: string, is_terminal: bool, is_canonical: bool, position: int}
     *
     * @throws DuplicateSlugException when the derived slug already exists
     */
    public function addCustomStatus(StatusMachine $machine, array $attrs): array
    {
        $name = (string) ($attrs['name'] ?? '');
        $slug = Str::slug($name);

        $existingSlugs = $machine->getStatuses()->pluck('slug')->all();

        if (in_array($slug, $existingSlugs, true)) {
            throw new DuplicateSlugException($slug);
        }

        $position = $machine->getStatuses()->count();

        $newStatus = [
            'slug' => $slug,
            'label' => $name,
            'color' => $attrs['color'] ?? 'gray',
            'is_terminal' => (bool) ($attrs['is_terminal'] ?? false),
            'is_canonical' => false,
            'position' => $position,
        ];

        $statuses = $machine->statuses ?? [];
        $statuses[] = $newStatus;
        $machine->statuses = $statuses;
        $machine->transitions = $this->addDefaultCustomTransitions(
            $machine->getStatuses()->all(),
            $machine->transitions ?? [],
            $slug,
        );
        $machine->save();

        return $newStatus;
    }

    /**
     * Delete a custom (non-canonical) status.
     *
     * Throws if the status is canonical or if any lead is currently assigned
     * to this status.
     *
     * @throws CanonicalStatusModificationException when the status is canonical
     * @throws StatusInUseException when leads are currently in this status
     */
    public function deleteCustomStatus(StatusMachine $machine, string $slug): void
    {
        $status = $machine->getStatuses()->firstWhere('slug', $slug);

        if (! $status) {
            throw new \InvalidArgumentException("Status '{$slug}' não encontrado no pipeline.");
        }

        if ($status['is_canonical'] ?? false) {
            throw new CanonicalStatusModificationException($slug);
        }

        $leadCount = Lead::query()
            ->where('tenant_id', $machine->tenant_id)
            ->where('status', $slug)
            ->count();

        if ($leadCount > 0) {
            throw new StatusInUseException($slug, $leadCount);
        }

        $machine->statuses = $machine->getStatuses()
            ->reject(fn (array $s) => $s['slug'] === $slug)
            ->values()
            ->all();
        $machine->transitions = collect($machine->transitions ?? [])
            ->reject(fn (array $t) => $t['from'] === $slug || $t['to'] === $slug)
            ->values()
            ->all();

        $machine->save();
    }

    /**
     * Add a transition between two existing statuses.
     *
     * @throws \InvalidArgumentException when either slug is unknown or transition already exists
     */
    public function addTransition(StatusMachine $machine, string $from, string $to): void
    {
        $existingSlugs = $machine->getStatuses()->pluck('slug')->all();

        if (! in_array($from, $existingSlugs, true)) {
            throw new \InvalidArgumentException("Status de origem '{$from}' não encontrado.");
        }

        if (! in_array($to, $existingSlugs, true)) {
            throw new \InvalidArgumentException("Status de destino '{$to}' não encontrado.");
        }

        $alreadyExists = collect($machine->transitions)->contains(
            fn (array $t) => $t['from'] === $from && $t['to'] === $to
        );

        if ($alreadyExists) {
            throw new \InvalidArgumentException("Transição '{$from}' → '{$to}' já existe.");
        }

        $transitions = $machine->transitions ?? [];
        $transitions[] = ['from' => $from, 'to' => $to];
        $machine->transitions = $transitions;
        $machine->save();
    }

    /**
     * Remove a transition between two statuses.
     *
     * Canonical (AI-dependent) transitions cannot be removed.
     *
     * @throws ProtectedTransitionException when the transition is canonical
     */
    public function removeTransition(StatusMachine $machine, string $from, string $to): void
    {
        if ($this->isProtectedTransition($from, $to)) {
            throw new ProtectedTransitionException($from, $to);
        }

        $machine->transitions = collect($machine->transitions)
            ->reject(fn (array $t) => $t['from'] === $from && $t['to'] === $to)
            ->values()
            ->all();

        $machine->save();
    }

    /**
     * Reorder statuses by providing the desired slug sequence.
     *
     * All slugs currently in the machine must be present in $slugsInOrder.
     * The `position` field on each status is updated to reflect the new order.
     *
     * @param  list<string>  $slugsInOrder
     *
     * @throws \InvalidArgumentException when the slug list does not match existing statuses
     */
    public function reorder(StatusMachine $machine, array $slugsInOrder): void
    {
        $existingSlugs = $machine->getStatuses()->pluck('slug')->sort()->values()->all();
        $providedSlugs = collect($slugsInOrder)->sort()->values()->all();

        if ($existingSlugs !== $providedSlugs) {
            throw new \InvalidArgumentException('A lista de slugs não corresponde aos statuses existentes no pipeline.');
        }

        $positionMap = array_flip($slugsInOrder);

        $statuses = $machine->getStatuses()
            ->map(function (array $status) use ($positionMap): array {
                $status['position'] = $positionMap[$status['slug']];

                return $status;
            })
            ->sortBy('position')
            ->values()
            ->all();

        $machine->statuses = $statuses;
        $machine->save();
    }

    /**
     * Reset the machine back to the default INSS pipeline.
     *
     * This is a destructive operation — all custom statuses and transitions
     * are removed. If any lead still uses a custom status, the reset is blocked
     * so commercial context does not become hidden from the pipeline UI.
     *
     * The controller should require an explicit `X-Confirm: 1` header before
     * calling this method.
     */
    public function resetToDefault(StatusMachine $machine): void
    {
        $customSlugs = $machine->getStatuses()
            ->reject(fn (array $status) => $status['is_canonical'] ?? false)
            ->pluck('slug')
            ->all();

        if ($customSlugs !== []) {
            $statusInUse = Lead::query()
                ->where('tenant_id', $machine->tenant_id)
                ->whereIn('status', $customSlugs)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->first();

            if ($statusInUse !== null) {
                throw new StatusInUseException((string) $statusInUse->status, (int) $statusInUse->count);
            }
        }

        $default = StatusMachine::default();
        $machine->statuses = $default->statuses;
        $machine->transitions = $default->transitions;
        $machine->initial_status = $default->initial_status;
        $machine->save();
    }

    /**
     * Check whether a from→to transition is in the protected canonical list.
     */
    public function isProtectedTransition(string $from, string $to): bool
    {
        foreach (self::PROTECTED_TRANSITIONS as $protected) {
            if ($protected['from'] === $from && $protected['to'] === $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add safe default transitions for a custom status.
     *
     * @param  list<array{slug: string, is_terminal?: bool}>  $statuses
     * @param  list<array{from: string, to: string}>  $transitions
     * @return list<array{from: string, to: string}>
     */
    private function addDefaultCustomTransitions(array $statuses, array $transitions, string $customSlug): array
    {
        $nonTerminalSlugs = collect($statuses)
            ->reject(fn (array $status) => ($status['is_terminal'] ?? false) === true)
            ->reject(fn (array $status) => in_array($status['slug'], StatusMachine::CUSTOM_STATUS_EXCLUDED_SLUGS, true))
            ->pluck('slug')
            ->all();

        foreach ($nonTerminalSlugs as $slug) {
            if ($slug !== $customSlug) {
                $transitions = $this->appendTransitionIfMissing($transitions, $slug, $customSlug);
                $transitions = $this->appendTransitionIfMissing($transitions, $customSlug, $slug);
            }
        }

        $transitions = $this->appendTransitionIfMissing($transitions, $customSlug, 'convertido');

        return $transitions;
    }

    /**
     * @param  list<array{from: string, to: string}>  $transitions
     * @return list<array{from: string, to: string}>
     */
    private function appendTransitionIfMissing(array $transitions, string $from, string $to): array
    {
        if ($from === $to) {
            return $transitions;
        }

        $alreadyExists = collect($transitions)->contains(
            fn (array $transition) => $transition['from'] === $from && $transition['to'] === $to
        );

        if (! $alreadyExists) {
            $transitions[] = ['from' => $from, 'to' => $to];
        }

        return $transitions;
    }
}
