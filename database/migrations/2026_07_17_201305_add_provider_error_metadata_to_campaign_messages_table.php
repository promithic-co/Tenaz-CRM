<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureColumn('provider_error_code', ['varchar'], function (Blueprint $table): void {
            $table->string('provider_error_code')->nullable()->after('error_subcode');
        });
        $this->ensureColumn('provider_http_status', ['integer', 'smallint'], function (Blueprint $table): void {
            $table->unsignedSmallInteger('provider_http_status')->nullable()->after('provider_error_code');
        });
        $this->ensureColumn('provider_error_type', ['varchar'], function (Blueprint $table): void {
            $table->string('provider_error_type', 100)->nullable()->after('provider_http_status');
        });
        $this->ensureColumn('provider_error_trace_id', ['varchar'], function (Blueprint $table): void {
            $table->string('provider_error_trace_id', 255)->nullable()->after('provider_error_type');
        });
        $this->ensureColumn('provider_attempt_token', ['uuid', 'varchar', 'char'], function (Blueprint $table): void {
            $table->uuid('provider_attempt_token')->nullable()->after('provider_attempted_at');
        });
        $this->ensureColumn('provider_attempt_lease_expires_at', ['datetime', 'timestamp'], function (Blueprint $table): void {
            $table->timestamp('provider_attempt_lease_expires_at')->nullable()->after('provider_attempt_token');
        });
        $this->ensureColumn('provider_retry_not_before', ['datetime', 'timestamp'], function (Blueprint $table): void {
            $table->timestamp('provider_retry_not_before')->nullable()->after('provider_attempt_lease_expires_at');
        });
    }

    public function down(): void
    {
        foreach ([
            'provider_retry_not_before',
            'provider_attempt_lease_expires_at',
            'provider_attempt_token',
            'provider_error_trace_id',
            'provider_error_type',
            'provider_http_status',
            'provider_error_code',
        ] as $column) {
            if (! Schema::hasColumn('campaign_messages', $column)) {
                continue;
            }

            Schema::table('campaign_messages', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    /**
     * @param  list<string>  $expectedTypes
     */
    private function ensureColumn(string $column, array $expectedTypes, Closure $definition): void
    {
        if (Schema::hasColumn('campaign_messages', $column)) {
            $actualType = Schema::getColumnType('campaign_messages', $column);

            if (! $this->isCompatibleColumnType($column, $actualType, $expectedTypes)) {
                throw new RuntimeException("campaign_messages.{$column} has unexpected type {$actualType}");
            }

            return;
        }

        Schema::table('campaign_messages', $definition);
    }

    /**
     * @param  list<string>  $expectedTypes
     */
    private function isCompatibleColumnType(string $column, string $actualType, array $expectedTypes = []): bool
    {
        if ($expectedTypes === []) {
            $expectedTypes = match ($column) {
                'provider_error_code', 'provider_error_type', 'provider_error_trace_id' => ['varchar'],
                'provider_http_status' => ['integer', 'smallint'],
                'provider_attempt_token' => ['uuid', 'varchar', 'char'],
                'provider_attempt_lease_expires_at', 'provider_retry_not_before' => ['datetime', 'timestamp'],
                default => [],
            };
        }

        return in_array(strtolower($actualType), $expectedTypes, true);
    }
};
