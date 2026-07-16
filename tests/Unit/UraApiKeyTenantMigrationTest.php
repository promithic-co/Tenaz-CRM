<?php

use App\Models\Agent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    expect(runUraMigrationFresh())->toBe(0);
});

test('migration test guard rejects file-backed sqlite before destructive call', function () {
    $originalDatabase = config('database.connections.sqlite.database');
    $migrateFreshWasCalled = false;

    try {
        config()->set('database.connections.sqlite.database', database_path('database.sqlite'));

        expect(fn () => runUraMigrationFresh(function () use (&$migrateFreshWasCalled): int {
            $migrateFreshWasCalled = true;

            return 0;
        }))->toThrow(LogicException::class, ':memory:');
    } finally {
        config()->set('database.connections.sqlite.database', $originalDatabase);
    }

    expect($migrateFreshWasCalled)->toBeFalse()
        ->and(config('database.connections.sqlite.database'))->toBe($originalDatabase);
});

function runUraMigrationFresh(?Closure $migrateFresh = null): int
{
    assertUraMigrationTestDatabaseIsInMemorySqlite();

    if ($migrateFresh !== null) {
        return $migrateFresh();
    }

    return Artisan::call('migrate:fresh', [
        '--force' => true,
        '--no-interaction' => true,
    ]);
}

function assertUraMigrationTestDatabaseIsInMemorySqlite(): void
{
    if (
        ! app()->environment('testing')
        || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.database') !== ':memory:'
    ) {
        throw new LogicException('URA migration tests require the testing environment with the default SQLite database set to :memory:.');
    }
}

function rebuildLegacyUraApiKeysTable(): void
{
    Schema::drop('ura_api_keys');

    Schema::create('ura_api_keys', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
        $table->foreignId('whatsapp_template_id')
            ->nullable()
            ->constrained('whatsapp_templates')
            ->nullOnDelete();
        $table->string('name');
        $table->string('key_hash')->unique();
        $table->string('key_preview', 8);
        $table->boolean('active')->default(true);
        $table->timestamp('last_used_at')->nullable();
        $table->timestamps();
        $table->index('tenant_id');
    });
}

function uraCanonicalTenantMigration(): Migration
{
    return require database_path('migrations/2026_07_15_003615_canonicalize_ura_api_key_tenant_ids.php');
}

function uraCanonicalTenantMigrationSql(string $method): string
{
    $migration = uraCanonicalTenantMigration();
    $reflection = new ReflectionMethod($migration, $method);

    return $reflection->invoke($migration);
}

test('mysql migration locks every table name and alias used by the canonical copy', function () {
    expect(uraCanonicalTenantMigrationSql('mysqlLockStatement'))->toBe(
        'LOCK TABLES `ura_api_keys` WRITE, `ura_api_keys` AS `ura` READ, `agents` AS `agent` READ, `whatsapp_templates` AS `template` READ, `tenants` READ, `ura_api_keys_canonical_shadow` WRITE, `ura_api_keys_canonical_shadow` AS `shadow` READ',
    );
});

test('postgres migration locks source and parent tables before preflight', function () {
    $source = file_get_contents(database_path('migrations/2026_07_15_003615_canonicalize_ura_api_key_tenant_ids.php'));
    $postgresMethod = substr(
        $source,
        strpos($source, 'private function migratePostgres(): void'),
        strpos($source, 'private function migrateMysql(): void') - strpos($source, 'private function migratePostgres(): void'),
    );

    $sourceLockPosition = strpos($postgresMethod, 'postgresSourceLockStatement');
    $parentLockPosition = strpos($postgresMethod, 'postgresParentLockStatement');
    $preflightPosition = strpos($postgresMethod, '$this->preflight()');

    expect(uraCanonicalTenantMigrationSql('postgresSourceLockStatement'))
        ->toBe('LOCK TABLE ura_api_keys IN ACCESS EXCLUSIVE MODE')
        ->and(uraCanonicalTenantMigrationSql('postgresParentLockStatement'))
        ->toBe('LOCK TABLE agents, whatsapp_templates, tenants IN SHARE MODE')
        ->and($sourceLockPosition)->toBeInt()->toBeLessThan($parentLockPosition)
        ->and($parentLockPosition)->toBeInt()->toBeLessThan($preflightPosition);
});

test('sqlite migration copies every column exactly and continues ids after the atomic swap', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $owner->tenants()->attach($tenant->id, ['role' => 'owner']);
    $legacyCreator = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $owner->id,
        'tenant_id' => (string) $tenant->id,
    ]);
    $template = WhatsappTemplate::factory()->create(['tenant_id' => $tenant->id]);
    $timestamp = now()->startOfSecond();

    rebuildLegacyUraApiKeysTable();

    DB::table('ura_api_keys')->insert([
        [
            'id' => 17,
            'tenant_id' => $legacyCreator->id,
            'agent_id' => $agent->id,
            'whatsapp_template_id' => $template->id,
            'name' => 'Legacy configured key',
            'key_hash' => hash('sha256', 'legacy-configured'),
            'key_preview' => 'figured1',
            'active' => true,
            'last_used_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ],
        [
            'id' => 41,
            'tenant_id' => $legacyCreator->id,
            'agent_id' => $agent->id,
            'whatsapp_template_id' => null,
            'name' => 'Legacy nullable key',
            'key_hash' => hash('sha256', 'legacy-nullable'),
            'key_preview' => 'nullable',
            'active' => false,
            'last_used_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ],
    ]);

    $before = DB::table('ura_api_keys')->orderBy('id')->get();

    uraCanonicalTenantMigration()->up();

    $after = DB::table('ura_api_keys')->orderBy('id')->get();

    expect($after)->toHaveCount(2);

    foreach ($after as $index => $row) {
        expect((string) $row->tenant_id)->toBe((string) $tenant->id);

        foreach ([
            'id',
            'agent_id',
            'whatsapp_template_id',
            'name',
            'key_hash',
            'key_preview',
            'active',
            'last_used_at',
            'created_at',
            'updated_at',
        ] as $column) {
            expect($row->{$column})->toBe($before[$index]->{$column});
        }
    }

    $tenantForeignKey = collect(DB::select("PRAGMA foreign_key_list('ura_api_keys')"))
        ->first(fn (object $foreignKey): bool => $foreignKey->from === 'tenant_id');

    expect($tenantForeignKey)->not->toBeNull()
        ->and($tenantForeignKey->table)->toBe('tenants');

    $newId = DB::table('ura_api_keys')->insertGetId([
        'tenant_id' => $tenant->id,
        'agent_id' => $agent->id,
        'whatsapp_template_id' => null,
        'name' => 'First canonical insert',
        'key_hash' => hash('sha256', 'first-canonical'),
        'key_preview' => 'nonical1',
        'active' => true,
        'last_used_at' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    expect($newId)->toBe(42)
        ->and(fn () => uraCanonicalTenantMigration()->down())
        ->toThrow(LogicException::class, 'irreversible');
});

test('sqlite migration rejects a foreign template and leaves the source untouched', function () {
    $tenant = Tenant::factory()->create();
    $foreignTenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $legacyCreator = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $owner->id,
        'tenant_id' => (string) $tenant->id,
    ]);
    $foreignTemplate = WhatsappTemplate::factory()->create(['tenant_id' => $foreignTenant->id]);

    rebuildLegacyUraApiKeysTable();

    DB::table('ura_api_keys')->insert([
        'tenant_id' => $legacyCreator->id,
        'agent_id' => $agent->id,
        'whatsapp_template_id' => $foreignTemplate->id,
        'name' => 'Foreign template',
        'key_hash' => hash('sha256', 'foreign-template'),
        'key_preview' => 'template',
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => uraCanonicalTenantMigration()->up())
        ->toThrow(RuntimeException::class, 'another tenant');

    expect(DB::table('ura_api_keys')->value('tenant_id'))->toBe($legacyCreator->id)
        ->and(Schema::hasTable('ura_api_keys_canonical_shadow'))->toBeFalse()
        ->and(Schema::hasTable('ura_api_keys_legacy_backup'))->toBeFalse();
});

test('sqlite migration rejects an agent without a canonical tenant and leaves the source untouched', function (mixed $invalidTenantId) {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $legacyCreator = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $owner->id,
        'tenant_id' => (string) $tenant->id,
    ]);
    DB::table('agents')->where('id', $agent->id)->update(['tenant_id' => $invalidTenantId]);

    rebuildLegacyUraApiKeysTable();

    DB::table('ura_api_keys')->insert([
        'tenant_id' => $legacyCreator->id,
        'agent_id' => $agent->id,
        'whatsapp_template_id' => null,
        'name' => 'Invalid agent tenant',
        'key_hash' => hash('sha256', 'invalid-agent-'.serialize($invalidTenantId)),
        'key_preview' => 'invalid1',
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => uraCanonicalTenantMigration()->up())
        ->toThrow(RuntimeException::class);

    expect(DB::table('ura_api_keys')->value('tenant_id'))->toBe($legacyCreator->id)
        ->and(Schema::hasTable('ura_api_keys_canonical_shadow'))->toBeFalse()
        ->and(Schema::hasTable('ura_api_keys_legacy_backup'))->toBeFalse();
})->with([
    'null tenant' => null,
    'non numeric tenant' => 'tenant-x',
    'missing tenant' => '999999',
]);
