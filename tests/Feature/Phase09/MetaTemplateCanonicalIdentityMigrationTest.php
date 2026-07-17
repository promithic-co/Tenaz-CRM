<?php

use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('enforces the canonical Meta template identity at the database boundary', function () {
    $index = collect(Schema::getIndexes('whatsapp_templates'))
        ->firstWhere('name', 'wa_templates_canonical_meta_identity_unique');

    expect($index)->not->toBeNull()
        ->and($index['unique'])->toBeTrue()
        ->and($index['columns'])->toBe([
            'tenant_id',
            'whatsapp_instance_id',
            'kind',
            'meta_template_name',
            'language',
        ]);
});

it('rejects two rows with the same canonical Meta identity', function () {
    $user = userWithTenant();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
    ]);
    $identity = [
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'kind' => 'meta_hsm',
        'meta_template_name' => 'same_meta_identity',
        'language' => 'pt_BR',
    ];

    WhatsappTemplate::factory()->create($identity + ['name' => 'Internal name one']);

    expect(fn () => WhatsappTemplate::factory()->create(
        $identity + ['name' => 'Internal name two']
    ))->toThrow(QueryException::class);
});

it('is retry-safe when the exact canonical index already exists', function () {
    $migrationFiles = glob(database_path('migrations/*_enforce_meta_template_canonical_identity.php'));
    $migration = require $migrationFiles[0];

    $migration->up();

    $matchingIndexes = collect(Schema::getIndexes('whatsapp_templates'))
        ->where('name', 'wa_templates_canonical_meta_identity_unique');

    expect($matchingIndexes)->toHaveCount(1)
        ->and($matchingIndexes->first()['unique'])->toBeTrue()
        ->and($matchingIndexes->first()['columns'])->toBe([
            'tenant_id',
            'whatsapp_instance_id',
            'kind',
            'meta_template_name',
            'language',
        ]);
});

it('recognizes an exact partial DDL index as a completed step', function () {
    withCanonicalIdentityMigrationDatabase(function (object $migration): void {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'whatsapp_instance_id', 'kind', 'meta_template_name', 'language'],
                'wa_templates_canonical_meta_identity_unique',
            );
        });

        $migration->up();

        expect(collect(Schema::getIndexes('whatsapp_templates'))
            ->where('name', 'wa_templates_canonical_meta_identity_unique'))
            ->toHaveCount(1);
    });
});

it('fails closed when the canonical index name has an incompatible signature', function () {
    withCanonicalIdentityMigrationDatabase(function (object $migration): void {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'kind'],
                'wa_templates_canonical_meta_identity_unique',
            );
        });

        expect(fn () => $migration->up())->toThrow(
            RuntimeException::class,
            'exists with an incompatible definition'
        );

        expect(collect(Schema::getIndexes('whatsapp_templates'))
            ->firstWhere('name', 'wa_templates_canonical_meta_identity_unique'))
            ->not->toBeNull();
    });
});

it('fails closed when the canonical index has the right columns but is not unique', function () {
    withCanonicalIdentityMigrationDatabase(function (object $migration): void {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'whatsapp_instance_id', 'kind', 'meta_template_name', 'language'],
                'wa_templates_canonical_meta_identity_unique',
            );
        });

        expect(fn () => $migration->up())->toThrow(
            RuntimeException::class,
            'exists with an incompatible definition'
        );
    });
});

it('makes rollback a safe no-op when the canonical index is absent', function () {
    withCanonicalIdentityMigrationDatabase(function (object $migration): void {
        $migration->down();

        expect(collect(Schema::getIndexes('whatsapp_templates'))
            ->firstWhere('name', 'wa_templates_canonical_meta_identity_unique'))
            ->toBeNull();
    });
});

it('refuses to drop an incompatible index during rollback', function () {
    withCanonicalIdentityMigrationDatabase(function (object $migration): void {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'kind'],
                'wa_templates_canonical_meta_identity_unique',
            );
        });

        expect(fn () => $migration->down())->toThrow(
            RuntimeException::class,
            'exists with an incompatible definition'
        );

        expect(collect(Schema::getIndexes('whatsapp_templates'))
            ->firstWhere('name', 'wa_templates_canonical_meta_identity_unique'))
            ->not->toBeNull();
    });
});

it('drops only the exact canonical index during rollback', function () {
    withCanonicalIdentityMigrationDatabase(function (object $migration): void {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'whatsapp_instance_id', 'kind', 'meta_template_name', 'language'],
                'wa_templates_canonical_meta_identity_unique',
            );
        });

        $migration->down();

        expect(collect(Schema::getIndexes('whatsapp_templates'))
            ->firstWhere('name', 'wa_templates_canonical_meta_identity_unique'))
            ->toBeNull();
    });
});

it('fails closed before DDL when canonical Meta identities are duplicated', function () {
    $originalDefault = DB::getDefaultConnection();
    $connectionName = 'canonical_identity_preflight';

    config()->set("database.connections.{$connectionName}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::setDefaultConnection($connectionName);

    try {
        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_instance_id')->nullable();
            $table->string('kind');
            $table->string('meta_template_name')->nullable();
            $table->string('language');
        });

        DB::table('whatsapp_templates')->insert([
            [
                'tenant_id' => 7,
                'whatsapp_instance_id' => 19,
                'kind' => 'meta_hsm',
                'meta_template_name' => 'duplicate_template',
                'language' => 'pt_BR',
            ],
            [
                'tenant_id' => 7,
                'whatsapp_instance_id' => 19,
                'kind' => 'meta_hsm',
                'meta_template_name' => 'duplicate_template',
                'language' => 'pt_BR',
            ],
        ]);

        $migrationFiles = glob(database_path('migrations/*_enforce_meta_template_canonical_identity.php'));

        expect($migrationFiles)->toHaveCount(1);

        $migration = require $migrationFiles[0];

        expect(fn () => $migration->up())->toThrow(
            RuntimeException::class,
            'Canonical Meta template identity migration aborted'
        );

        expect(collect(Schema::getIndexes('whatsapp_templates'))
            ->firstWhere('name', 'wa_templates_canonical_meta_identity_unique'))->toBeNull();
    } finally {
        DB::purge($connectionName);
        DB::setDefaultConnection($originalDefault);
    }
});

function withCanonicalIdentityMigrationDatabase(Closure $callback): void
{
    $originalDefault = DB::getDefaultConnection();
    $connectionName = 'canonical_identity_partial_ddl';

    config()->set("database.connections.{$connectionName}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::setDefaultConnection($connectionName);

    try {
        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_instance_id')->nullable();
            $table->string('kind');
            $table->string('meta_template_name')->nullable();
            $table->string('language');
        });

        $migrationFiles = glob(database_path('migrations/*_enforce_meta_template_canonical_identity.php'));
        $callback(require $migrationFiles[0]);
    } finally {
        DB::purge($connectionName);
        DB::setDefaultConnection($originalDefault);
    }
}
