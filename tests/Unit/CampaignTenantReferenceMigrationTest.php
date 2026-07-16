<?php

use App\Models\ContactList;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    expect(runCampaignMigrationFresh())->toBe(0);
});

afterEach(function () {
    runCampaignMigrationFresh();
});

it('rejects file-backed sqlite before destructive migration reset', function () {
    $originalDatabase = config('database.connections.sqlite.database');
    $migrateFreshWasCalled = false;

    try {
        config()->set('database.connections.sqlite.database', database_path('database.sqlite'));

        expect(fn () => runCampaignMigrationFresh(function () use (&$migrateFreshWasCalled): int {
            $migrateFreshWasCalled = true;

            return 0;
        }))->toThrow(LogicException::class, ':memory:');
    } finally {
        config()->set('database.connections.sqlite.database', $originalDatabase);
    }

    expect($migrateFreshWasCalled)->toBeFalse()
        ->and(config('database.connections.sqlite.database'))->toBe($originalDatabase);
});

function runCampaignMigrationFresh(?Closure $migrateFresh = null): int
{
    assertCampaignMigrationTestDatabaseIsInMemorySqlite();

    if ($migrateFresh !== null) {
        return $migrateFresh();
    }

    return Artisan::call('migrate:fresh', [
        '--force' => true,
        '--no-interaction' => true,
    ]);
}

function assertCampaignMigrationTestDatabaseIsInMemorySqlite(): void
{
    if (
        ! app()->environment('testing')
        || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.database') !== ':memory:'
    ) {
        throw new LogicException('Campaign migration tests require the testing environment with the default SQLite database set to :memory:.');
    }
}

it('aborts before ddl when an existing campaign references a foreign contact list', function () {
    dropCampaignTenantCompositeForeignKeys();
    $fixtures = campaignTenantMigrationFixtures();

    DB::table('campaigns')->insert(campaignTenantMigrationAttributes(
        tenantId: $fixtures['owner_tenant_id'],
        contactListId: $fixtures['foreign_contact_list_id'],
        templateId: $fixtures['template_id'],
        instanceId: $fixtures['instance_id'],
    ));

    expect(fn () => campaignTenantReferenceMigration()->up())
        ->toThrow(RuntimeException::class, 'references contact list');

    expect(DB::table('campaigns')->count())->toBe(1);
});

it('aborts before ddl when an existing campaign references a foreign template', function () {
    dropCampaignTenantCompositeForeignKeys();
    $fixtures = campaignTenantMigrationFixtures();

    DB::table('campaigns')->insert(campaignTenantMigrationAttributes(
        tenantId: $fixtures['owner_tenant_id'],
        contactListId: $fixtures['contact_list_id'],
        templateId: $fixtures['foreign_template_id'],
        instanceId: $fixtures['instance_id'],
    ));

    expect(fn () => campaignTenantReferenceMigration()->up())
        ->toThrow(RuntimeException::class, 'references WhatsApp template');

    expect(DB::table('campaigns')->count())->toBe(1);
});

it('rolls back only the composite integrity constraints on sqlite', function () {
    campaignTenantReferenceMigration()->down();
    $fixtures = campaignTenantMigrationFixtures();

    DB::table('campaigns')->insert(campaignTenantMigrationAttributes(
        tenantId: $fixtures['owner_tenant_id'],
        contactListId: $fixtures['foreign_contact_list_id'],
        templateId: $fixtures['foreign_template_id'],
        instanceId: $fixtures['instance_id'],
    ));

    expect(DB::table('campaigns')->count())->toBe(1)
        ->and(fn () => DB::table('campaigns')->insert(campaignTenantMigrationAttributes(
            tenantId: $fixtures['owner_tenant_id'],
            contactListId: PHP_INT_MAX,
            templateId: $fixtures['template_id'],
            instanceId: $fixtures['instance_id'],
        )))->toThrow(QueryException::class);
});

it('converges when up is retried after every completed ddl step on sqlite', function (int $completedSteps) {
    campaignTenantReferenceMigration()->down();
    applyCampaignTenantIntegritySteps($completedSteps);

    campaignTenantReferenceMigration()->up();

    expectCampaignTenantIntegrityObjectsToExist();

    $fixtures = campaignTenantMigrationFixtures();

    expect(fn () => DB::table('campaigns')->insert(campaignTenantMigrationAttributes(
        tenantId: $fixtures['owner_tenant_id'],
        contactListId: $fixtures['foreign_contact_list_id'],
        templateId: $fixtures['template_id'],
        instanceId: $fixtures['instance_id'],
    )))->toThrow(QueryException::class);
})->with([
    'after contact list unique index' => 1,
    'after both parent unique indexes' => 2,
    'after contact list composite foreign key' => 3,
    'after all named objects' => 4,
]);

it('converges when down is retried after every completed removal step on sqlite', function (int $completedRemovalSteps) {
    removeCampaignTenantIntegritySteps($completedRemovalSteps);

    campaignTenantReferenceMigration()->down();

    expectCampaignTenantIntegrityObjectsNotToExist();
})->with([
    'after contact list composite foreign key removal' => 1,
    'after both composite foreign key removals' => 2,
    'after contact list unique index removal' => 3,
    'after all named object removals' => 4,
]);

it('fails loudly when a named parent index has a different definition', function () {
    campaignTenantReferenceMigration()->down();

    Schema::table('contact_lists', function (Blueprint $table): void {
        $table->unique(['id', 'tenant_id'], 'cl_tenant_id_id_unique');
    });

    expect(fn () => campaignTenantReferenceMigration()->up())
        ->toThrow(QueryException::class, 'cl_tenant_id_id_unique');

    expect(Schema::hasIndex('whatsapp_templates', 'wt_tenant_id_id_unique', 'unique'))->toBeFalse();
});

function campaignTenantReferenceMigration(): Migration
{
    return require database_path('migrations/2026_07_15_010008_enforce_campaign_tenant_reference_integrity.php');
}

function dropCampaignTenantCompositeForeignKeys(): void
{
    $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

    Schema::table('campaigns', function (Blueprint $table) use ($isSqlite): void {
        $table->dropForeign($isSqlite
            ? ['tenant_id', 'contact_list_id']
            : 'camp_tenant_contact_list_fk');
        $table->dropForeign($isSqlite
            ? ['tenant_id', 'whatsapp_template_id']
            : 'camp_tenant_template_fk');
    });
}

function applyCampaignTenantIntegritySteps(int $completedSteps): void
{
    if ($completedSteps >= 1) {
        Schema::table('contact_lists', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id'], 'cl_tenant_id_id_unique');
        });
    }

    if ($completedSteps >= 2) {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id'], 'wt_tenant_id_id_unique');
        });
    }

    if ($completedSteps >= 3) {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'contact_list_id'], 'camp_tenant_contact_list_fk')
                ->references(['tenant_id', 'id'])
                ->on('contact_lists')
                ->cascadeOnDelete();
        });
    }

    if ($completedSteps >= 4) {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'whatsapp_template_id'], 'camp_tenant_template_fk')
                ->references(['tenant_id', 'id'])
                ->on('whatsapp_templates')
                ->cascadeOnDelete();
        });
    }
}

function removeCampaignTenantIntegritySteps(int $completedSteps): void
{
    if ($completedSteps >= 1) {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::table('campaigns', function (Blueprint $table) use ($isSqlite): void {
            $table->dropForeign($isSqlite
                ? ['tenant_id', 'contact_list_id']
                : 'camp_tenant_contact_list_fk');
        });
    }

    if ($completedSteps >= 2) {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::table('campaigns', function (Blueprint $table) use ($isSqlite): void {
            $table->dropForeign($isSqlite
                ? ['tenant_id', 'whatsapp_template_id']
                : 'camp_tenant_template_fk');
        });
    }

    if ($completedSteps >= 3) {
        Schema::table('contact_lists', function (Blueprint $table): void {
            $table->dropUnique('cl_tenant_id_id_unique');
        });
    }

    if ($completedSteps >= 4) {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropUnique('wt_tenant_id_id_unique');
        });
    }
}

function expectCampaignTenantIntegrityObjectsToExist(): void
{
    expect(Schema::hasIndex('contact_lists', 'cl_tenant_id_id_unique', 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('whatsapp_templates', 'wt_tenant_id_id_unique', 'unique'))->toBeTrue()
        ->and(campaignTenantForeignKeyExists(
            'camp_tenant_contact_list_fk',
            ['tenant_id', 'contact_list_id'],
            'contact_lists',
            ['tenant_id', 'id'],
        ))->toBeTrue()
        ->and(campaignTenantForeignKeyExists(
            'camp_tenant_template_fk',
            ['tenant_id', 'whatsapp_template_id'],
            'whatsapp_templates',
            ['tenant_id', 'id'],
        ))->toBeTrue();
}

function expectCampaignTenantIntegrityObjectsNotToExist(): void
{
    expect(Schema::hasIndex('contact_lists', 'cl_tenant_id_id_unique', 'unique'))->toBeFalse()
        ->and(Schema::hasIndex('whatsapp_templates', 'wt_tenant_id_id_unique', 'unique'))->toBeFalse()
        ->and(campaignTenantForeignKeyExists(
            'camp_tenant_contact_list_fk',
            ['tenant_id', 'contact_list_id'],
            'contact_lists',
            ['tenant_id', 'id'],
        ))->toBeFalse()
        ->and(campaignTenantForeignKeyExists(
            'camp_tenant_template_fk',
            ['tenant_id', 'whatsapp_template_id'],
            'whatsapp_templates',
            ['tenant_id', 'id'],
        ))->toBeFalse();
}

/**
 * @param  list<string>  $columns
 * @param  list<string>  $foreignColumns
 */
function campaignTenantForeignKeyExists(
    string $name,
    array $columns,
    string $foreignTable,
    array $foreignColumns,
): bool {
    $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

    foreach (Schema::getForeignKeys('campaigns') as $foreignKey) {
        if (! $isSqlite && $foreignKey['name'] !== $name) {
            continue;
        }

        if (
            $foreignKey['columns'] === $columns
            && $foreignKey['foreign_table'] === $foreignTable
            && $foreignKey['foreign_columns'] === $foreignColumns
            && $foreignKey['on_delete'] === 'cascade'
        ) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{
 *     owner_tenant_id: int,
 *     instance_id: int,
 *     contact_list_id: int,
 *     foreign_contact_list_id: int,
 *     template_id: int,
 *     foreign_template_id: int
 * }
 */
function campaignTenantMigrationFixtures(): array
{
    $owner = User::factory()->create();
    $ownerTenantId = (int) $owner->tenantId;
    $foreignOwner = User::factory()->create();
    $foreignTenantId = (int) $foreignOwner->tenantId;

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $owner->id,
        'tenant_id' => $ownerTenantId,
    ]);
    $contactList = ContactList::factory()->create(['tenant_id' => $ownerTenantId]);
    $foreignContactList = ContactList::factory()->create(['tenant_id' => $foreignTenantId]);
    $template = WhatsappTemplate::factory()->create(['tenant_id' => $ownerTenantId]);
    $foreignTemplate = WhatsappTemplate::factory()->create(['tenant_id' => $foreignTenantId]);

    return [
        'owner_tenant_id' => $ownerTenantId,
        'instance_id' => $instance->id,
        'contact_list_id' => $contactList->id,
        'foreign_contact_list_id' => $foreignContactList->id,
        'template_id' => $template->id,
        'foreign_template_id' => $foreignTemplate->id,
    ];
}

/**
 * @return array<string, mixed>
 */
function campaignTenantMigrationAttributes(int $tenantId, int $contactListId, int $templateId, int $instanceId): array
{
    return [
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $instanceId,
        'contact_list_id' => $contactListId,
        'whatsapp_template_id' => $templateId,
        'name' => 'Preflight integrity campaign',
        'created_at' => now(),
        'updated_at' => now(),
    ];
}
