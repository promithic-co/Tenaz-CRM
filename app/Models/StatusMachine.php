<?php

namespace App\Models;

use App\Observers\StatusMachineObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class StatusMachine extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::observe(StatusMachineObserver::class);
    }

    /**
     * Canonical slugs used by AI tools (AtualizarStatusLeadTool, EscalarParaHumanoTool,
     * ConversationAutomationService). These MUST NOT be renamed or removed as AI
     * hardcodes them. Only label, color, and position are editable on canonical statuses.
     *
     * @var list<string>
     */
    public const CANONICAL_SLUGS = [
        'novo',
        'qualificado',
        'sem_credito',
        'desqualificado',
        'escalado',
        'convertido',
        'optou_sair',
    ];

    /**
     * Slugs excluded from automatic custom-status transition generation.
     *
     * @var list<string>
     */
    public const CUSTOM_STATUS_EXCLUDED_SLUGS = [
        'optou_sair',
    ];

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'statuses',
        'transitions',
        'initial_status',
    ];

    protected function casts(): array
    {
        return [
            'statuses' => 'array',
            'transitions' => 'array',
        ];
    }

    /**
     * Find the status machine for a tenant, with fallback to the default INSS machine.
     *
     * Uses a request-level container binding as a lightweight per-request cache so that
     * multiple calls within a single HTTP request (e.g. Lead::canTransitionTo() called
     * in a loop) do not each execute a SQL query.
     *
     * The binding is invalidated by StatusMachineObserver::saved() whenever the machine
     * is persisted, ensuring the next call fetches the fresh value from the database.
     */
    public static function forTenant(string $tenantId): static
    {
        $key = "status_machine.{$tenantId}";

        if (app()->bound($key)) {
            return app($key);
        }

        $machine = static::where('tenant_id', $tenantId)->first()
            ?? static::default();

        app()->instance($key, $machine);

        return $machine;
    }

    /** Flush the request-level cache for this tenant. Called by StatusMachineObserver. */
    public static function flushCache(string $tenantId): void
    {
        app()->forgetInstance("status_machine.{$tenantId}");
    }

    /**
     * The built-in status machine (INSS consigned credit).
     *
     * Includes `is_canonical` and `position` on every status so that callers
     * (UI, service layer) can rely on these keys being present on both persisted
     * and in-memory default machines.
     */
    public static function default(): static
    {
        $model = new static;
        $model->statuses = [
            ['slug' => 'novo', 'label' => 'Novo', 'color' => 'gray', 'is_terminal' => false, 'is_canonical' => true, 'position' => 0],
            ['slug' => 'qualificado', 'label' => 'Qualificado', 'color' => 'green', 'is_terminal' => false, 'is_canonical' => true, 'position' => 1],
            ['slug' => 'sem_credito', 'label' => 'Sem Crédito', 'color' => 'yellow', 'is_terminal' => false, 'is_canonical' => true, 'position' => 2],
            ['slug' => 'desqualificado', 'label' => 'Desqualificado', 'color' => 'orange', 'is_terminal' => false, 'is_canonical' => true, 'position' => 3],
            ['slug' => 'escalado', 'label' => 'Escalado', 'color' => 'blue', 'is_terminal' => false, 'is_canonical' => true, 'position' => 4],
            ['slug' => 'convertido', 'label' => 'Convertido', 'color' => 'purple', 'is_terminal' => true, 'is_canonical' => true, 'position' => 5],
            ['slug' => 'optou_sair', 'label' => 'Optou por Sair', 'color' => 'red', 'is_terminal' => true, 'is_canonical' => true, 'position' => 6],
        ];
        $model->transitions = [
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
        $model->initial_status = 'novo';

        return $model;
    }

    public function canTransition(string $from, string $to): bool
    {
        foreach ($this->transitions as $t) {
            if ($t['from'] === $from && $t['to'] === $to) {
                return true;
            }
        }

        return false;
    }

    public function getStatuses(): Collection
    {
        return collect($this->statuses);
    }

    public function getTerminalStatuses(): array
    {
        return collect($this->statuses)
            ->where('is_terminal', true)
            ->pluck('slug')
            ->all();
    }

    public function getAvailableTransitions(string $currentStatus): array
    {
        return collect($this->transitions)
            ->where('from', $currentStatus)
            ->pluck('to')
            ->all();
    }
}
