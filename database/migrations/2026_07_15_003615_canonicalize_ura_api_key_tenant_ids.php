<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    private const SOURCE_TABLE = 'ura_api_keys';

    private const SHADOW_TABLE = 'ura_api_keys_canonical_shadow';

    private const BACKUP_TABLE = 'ura_api_keys_legacy_backup';

    /** @var list<string> */
    private const COLUMNS = [
        'id',
        'tenant_id',
        'agent_id',
        'whatsapp_template_id',
        'name',
        'key_hash',
        'key_preview',
        'active',
        'last_used_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            throw new RuntimeException('Cannot canonicalize URA API keys because the source table is missing.');
        }

        $this->assertSwapArtifactsAreAbsent();

        match (DB::getDriverName()) {
            'sqlite' => $this->migrateSqlite(),
            'pgsql' => $this->migratePostgres(),
            'mysql', 'mariadb' => $this->migrateMysql(),
            default => throw new RuntimeException('Unsupported database driver for URA tenant canonicalization.'),
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException(
            'URA tenant canonicalization is irreversible because creator user IDs cannot be reconstructed. Restore a snapshot or deploy a forward fix.'
        );
    }

    private function migrateSqlite(): void
    {
        $foreignKeysEnabled = (int) DB::scalar('PRAGMA foreign_keys');

        DB::statement('PRAGMA foreign_keys = OFF');
        DB::unprepared('BEGIN IMMEDIATE');

        try {
            $this->preflight();
            $this->createShadowTable();
            $this->copyCanonicalRows();
            $this->verifyExactCopy();
            $this->swapTransactionalTables();
            DB::unprepared('COMMIT');
        } catch (Throwable $throwable) {
            DB::unprepared('ROLLBACK');

            throw $throwable;
        } finally {
            DB::statement('PRAGMA foreign_keys = '.($foreignKeysEnabled === 1 ? 'ON' : 'OFF'));
        }
    }

    private function migratePostgres(): void
    {
        DB::beginTransaction();

        try {
            DB::statement($this->postgresSourceLockStatement());
            DB::statement($this->postgresParentLockStatement());
            $this->preflight();
            $this->createShadowTable();
            $this->copyCanonicalRows();
            $this->verifyExactCopy();
            $this->swapTransactionalTables();
            $this->reseedPostgresIdentity();
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();

            throw $throwable;
        }
    }

    private function migrateMysql(): void
    {
        $locked = false;
        $swapped = false;
        $autocommit = (int) DB::scalar('SELECT @@autocommit');

        try {
            $this->createShadowTable();
            DB::statement('SET autocommit = 0');
            DB::statement($this->mysqlLockStatement());
            $locked = true;

            $this->preflight();
            $this->copyCanonicalRows();
            $this->verifyExactCopy();

            DB::statement(sprintf(
                'RENAME TABLE `%s` TO `%s`, `%s` TO `%s`',
                self::SOURCE_TABLE,
                self::BACKUP_TABLE,
                self::SHADOW_TABLE,
                self::SOURCE_TABLE,
            ));
            $swapped = true;
            DB::statement('DROP TABLE `'.self::BACKUP_TABLE.'`');
            DB::statement('COMMIT');
        } finally {
            if ($locked) {
                DB::statement('UNLOCK TABLES');
            }

            DB::statement('SET autocommit = '.$autocommit);

            if (! $swapped && Schema::hasTable(self::SHADOW_TABLE)) {
                Schema::drop(self::SHADOW_TABLE);
            }
        }
    }

    private function postgresSourceLockStatement(): string
    {
        return 'LOCK TABLE '.self::SOURCE_TABLE.' IN ACCESS EXCLUSIVE MODE';
    }

    private function postgresParentLockStatement(): string
    {
        return 'LOCK TABLE agents, whatsapp_templates, tenants IN SHARE MODE';
    }

    private function mysqlLockStatement(): string
    {
        return sprintf(
            'LOCK TABLES `%s` WRITE, `%s` AS `ura` READ, `agents` AS `agent` READ, `whatsapp_templates` AS `template` READ, `tenants` READ, `%s` WRITE, `%s` AS `shadow` READ',
            self::SOURCE_TABLE,
            self::SOURCE_TABLE,
            self::SHADOW_TABLE,
            self::SHADOW_TABLE,
        );
    }

    private function assertSwapArtifactsAreAbsent(): void
    {
        foreach ([self::SHADOW_TABLE, self::BACKUP_TABLE] as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException("Cannot canonicalize URA API keys while {$table} already exists.");
            }
        }
    }

    private function preflight(): void
    {
        $tenantIds = DB::table('tenants')
            ->pluck('id')
            ->mapWithKeys(fn (mixed $id): array => [(string) $id => true])
            ->all();

        $rows = DB::table(self::SOURCE_TABLE.' as ura')
            ->leftJoin('agents as agent', 'agent.id', '=', 'ura.agent_id')
            ->leftJoin('whatsapp_templates as template', 'template.id', '=', 'ura.whatsapp_template_id')
            ->orderBy('ura.id')
            ->get([
                'ura.id',
                'ura.whatsapp_template_id',
                'agent.id as agent_exists',
                'agent.tenant_id as agent_tenant_id',
                'template.id as template_exists',
                'template.tenant_id as template_tenant_id',
            ]);

        foreach ($rows as $row) {
            $tenantId = (string) ($row->agent_tenant_id ?? '');

            if ($row->agent_exists === null || preg_match('/^[1-9][0-9]*$/', $tenantId) !== 1) {
                throw new RuntimeException("URA API key {$row->id} has an agent without a canonical numeric tenant.");
            }

            if (! isset($tenantIds[$tenantId])) {
                throw new RuntimeException("URA API key {$row->id} resolves to missing tenant {$tenantId}.");
            }

            if ($row->whatsapp_template_id !== null && $row->template_exists === null) {
                throw new RuntimeException("URA API key {$row->id} references a missing WhatsApp template.");
            }

            if ($row->template_exists !== null && (string) $row->template_tenant_id !== $tenantId) {
                throw new RuntimeException("URA API key {$row->id} has a WhatsApp template from another tenant.");
            }
        }
    }

    private function createShadowTable(): void
    {
        Schema::create(self::SHADOW_TABLE, function (Blueprint $table): void {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->engine = 'InnoDB';
            }

            $table->bigIncrements('id');
            $table->foreignId('tenant_id');
            $table->foreignId('agent_id');
            $table->foreignId('whatsapp_template_id')->nullable();
            $table->string('name');
            $table->string('key_hash')->unique();
            $table->string('key_preview', 8);
            $table->boolean('active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
            $table->foreign('whatsapp_template_id')
                ->references('id')
                ->on('whatsapp_templates')
                ->nullOnDelete();
        });
    }

    private function copyCanonicalRows(): void
    {
        DB::table(self::SHADOW_TABLE)->insertUsing(
            self::COLUMNS,
            $this->canonicalSourceQuery(),
        );
    }

    private function canonicalSourceQuery(): Builder
    {
        $tenantExpression = match (DB::getDriverName()) {
            'mysql', 'mariadb' => 'CAST(agent.tenant_id AS UNSIGNED)',
            'pgsql' => 'CAST(agent.tenant_id AS BIGINT)',
            default => 'CAST(agent.tenant_id AS INTEGER)',
        };

        return DB::table(self::SOURCE_TABLE.' as ura')
            ->join('agents as agent', 'agent.id', '=', 'ura.agent_id')
            ->orderBy('ura.id')
            ->select([
                'ura.id',
                DB::raw($tenantExpression.' as tenant_id'),
                'ura.agent_id',
                'ura.whatsapp_template_id',
                'ura.name',
                'ura.key_hash',
                'ura.key_preview',
                'ura.active',
                'ura.last_used_at',
                'ura.created_at',
                'ura.updated_at',
            ]);
    }

    private function verifyExactCopy(): void
    {
        $sourceCount = DB::table(self::SOURCE_TABLE)->count();
        $shadowCount = DB::table(self::SHADOW_TABLE)->count();

        if ($sourceCount !== $shadowCount) {
            throw new RuntimeException("URA shadow copy row count mismatch: source={$sourceCount}, shadow={$shadowCount}.");
        }

        $tenantExpression = match (DB::getDriverName()) {
            'mysql', 'mariadb' => 'CAST(agent.tenant_id AS UNSIGNED)',
            'pgsql' => 'CAST(agent.tenant_id AS BIGINT)',
            default => 'CAST(agent.tenant_id AS INTEGER)',
        };
        $comparisons = [];

        foreach (self::COLUMNS as $column) {
            $expected = $column === 'tenant_id' ? $tenantExpression : "ura.{$column}";
            $comparisons[] = $this->nullSafeEquality("shadow.{$column}", $expected);
        }

        $allColumnsMatch = implode(' AND ', $comparisons);
        $forwardMismatch = DB::selectOne(sprintf(
            'SELECT 1 AS mismatch FROM %s ura JOIN agents agent ON agent.id = ura.agent_id LEFT JOIN %s shadow ON shadow.id = ura.id WHERE shadow.id IS NULL OR NOT (%s) LIMIT 1',
            self::SOURCE_TABLE,
            self::SHADOW_TABLE,
            $allColumnsMatch,
        ));
        $reverseMismatch = DB::selectOne(sprintf(
            'SELECT 1 AS mismatch FROM %s shadow LEFT JOIN %s ura ON ura.id = shadow.id LEFT JOIN agents agent ON agent.id = ura.agent_id WHERE ura.id IS NULL OR NOT (%s) LIMIT 1',
            self::SHADOW_TABLE,
            self::SOURCE_TABLE,
            $allColumnsMatch,
        ));

        if ($forwardMismatch !== null || $reverseMismatch !== null) {
            throw new RuntimeException('URA shadow copy failed exact column-by-column anti-join verification.');
        }
    }

    private function nullSafeEquality(string $left, string $right): string
    {
        return match (DB::getDriverName()) {
            'mysql', 'mariadb' => "{$left} <=> {$right}",
            'pgsql' => "{$left} IS NOT DISTINCT FROM {$right}",
            default => "{$left} IS {$right}",
        };
    }

    private function swapTransactionalTables(): void
    {
        Schema::rename(self::SOURCE_TABLE, self::BACKUP_TABLE);
        Schema::rename(self::SHADOW_TABLE, self::SOURCE_TABLE);
        Schema::drop(self::BACKUP_TABLE);
    }

    private function reseedPostgresIdentity(): void
    {
        $maxId = (int) (DB::table(self::SOURCE_TABLE)->max('id') ?? 0);

        if ($maxId === 0) {
            DB::statement("SELECT setval(pg_get_serial_sequence('ura_api_keys', 'id'), 1, false)");

            return;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('ura_api_keys', 'id'), {$maxId}, true)");
    }
};
