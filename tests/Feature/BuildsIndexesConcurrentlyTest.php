<?php

use App\Support\Database\BuildsIndexesConcurrently;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function concurrentIndexHelper(): object
{
    return new class
    {
        use BuildsIndexesConcurrently;

        /**
         * @param  list<string>  $columns
         */
        public function build(string $table, string $name, array $columns, bool $unique = false, ?string $where = null): void
        {
            $this->createIndexConcurrently($table, $name, $columns, $unique, $where);
        }

        public function teardown(string $name): void
        {
            $this->dropIndexConcurrently($name);
        }
    };
}

function indexExists(string $name): bool
{
    return DB::table('sqlite_master')->where('type', 'index')->where('name', $name)->exists();
}

test('concurrent-index migrations disable transaction wrapping (GROW-5)', function () {
    // CONCURRENTLY cannot run inside a transaction; if the property is dropped the
    // PostgreSQL deploy would fail, so guard every migration that builds an index this way.
    $migrations = [
        '2026_06_24_151349_add_lead_id_created_at_index_to_agent_interaction_events_table.php',
        '2026_06_24_152505_add_role_created_at_index_to_agent_conversation_messages_table.php',
        '2026_06_24_154224_add_inbound_provider_message_unique_to_conversation_timeline_messages_table.php',
    ];

    foreach ($migrations as $file) {
        $migration = require database_path("migrations/{$file}");
        expect($migration->withinTransaction)->toBeFalse("{$file} must disable transaction wrapping");
    }
});

test('createIndexConcurrently builds then drops a plain index on the current driver (GROW-5)', function () {
    DB::statement('CREATE TABLE grow5_demo (id integer primary key, a integer, b integer)');

    $helper = concurrentIndexHelper();
    $helper->build('grow5_demo', 'grow5_demo_ab_idx', ['a', 'b']);
    expect(indexExists('grow5_demo_ab_idx'))->toBeTrue();

    $helper->teardown('grow5_demo_ab_idx');
    expect(indexExists('grow5_demo_ab_idx'))->toBeFalse();
});

test('createIndexConcurrently is idempotent — a second build does not throw (GROW-5)', function () {
    DB::statement('CREATE TABLE grow5_idem (id integer primary key, a integer)');

    $helper = concurrentIndexHelper();
    $helper->build('grow5_idem', 'grow5_idem_a_idx', ['a']);

    expect(fn () => $helper->build('grow5_idem', 'grow5_idem_a_idx', ['a']))->not->toThrow(QueryException::class);
});

test('createIndexConcurrently with unique + where enforces a partial constraint (GROW-5)', function () {
    DB::statement('CREATE TABLE grow5_dup (id integer primary key, k text, active integer)');

    concurrentIndexHelper()->build('grow5_dup', 'grow5_dup_k_active_unique', ['k'], unique: true, where: 'active = 1');

    // Rows outside the predicate never collide.
    DB::table('grow5_dup')->insert(['k' => 'x', 'active' => 0]);
    DB::table('grow5_dup')->insert(['k' => 'x', 'active' => 0]);

    // First active row is fine; the duplicate active row trips the partial unique index.
    DB::table('grow5_dup')->insert(['k' => 'x', 'active' => 1]);

    expect(fn () => DB::table('grow5_dup')->insert(['k' => 'x', 'active' => 1]))
        ->toThrow(QueryException::class);

    expect(DB::table('grow5_dup')->count())->toBe(3);
});
