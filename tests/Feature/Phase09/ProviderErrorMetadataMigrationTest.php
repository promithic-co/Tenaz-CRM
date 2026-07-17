<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('provider metadata migration recovers from partial DDL and is idempotent in both directions', function (): void {
    $migration = require database_path('migrations/2026_07_17_201305_add_provider_error_metadata_to_campaign_messages_table.php');

    try {
        $migration->down();
        $migration->down();

        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->unsignedSmallInteger('provider_http_status')->nullable();
            $table->string('provider_error_type', 100)->nullable();
        });

        $migration->up();
        $migration->up();

        expect(Schema::hasColumns('campaign_messages', [
            'provider_error_code',
            'provider_http_status',
            'provider_error_type',
            'provider_error_trace_id',
            'provider_attempt_token',
            'provider_attempt_lease_expires_at',
            'provider_retry_not_before',
        ]))->toBeTrue()
            ->and(Schema::getColumnType('campaign_messages', 'provider_http_status'))->toBeIn(['integer', 'smallint'])
            ->and(Schema::getColumnType('campaign_messages', 'provider_error_type'))->toBe('varchar');
    } finally {
        $migration->down();
        $migration->up();
    }
});

test('provider attempt UUID migration contract accepts MySQL char 36 storage', function (): void {
    $migration = require database_path('migrations/2026_07_17_201305_add_provider_error_metadata_to_campaign_messages_table.php');
    $method = new ReflectionMethod($migration, 'isCompatibleColumnType');

    expect($method->invoke($migration, 'provider_attempt_token', 'char'))->toBeTrue()
        ->and($method->invoke($migration, 'provider_attempt_token', 'uuid'))->toBeTrue()
        ->and($method->invoke($migration, 'provider_attempt_token', 'varchar'))->toBeTrue()
        ->and($method->invoke($migration, 'provider_attempt_token', 'integer'))->toBeFalse();
});
