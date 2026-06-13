<?php

use App\Services\LegacyTenantKeyRealignmentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unifies legacy {@code tenant_id} strings (owner user id) with {@see \App\Models\Tenant::$id}
 * after {@code tenants} / {@code tenant_user} exist. Safe to re-run: no-op when already aligned.
 *
 * Also re-targets the foreign key on campaign-family tables from users → tenants.
 */
return new class extends Migration
{
    /** Tables that had foreignId('tenant_id')->constrained('users') and must be retargeted. */
    private const FK_TABLES = [
        'campaigns',
        'contact_lists',
        'whatsapp_templates',
        'voice_instances',
        'voice_campaigns',
        'evolution_campaigns',
    ];

    public function up(): void
    {
        foreach (self::FK_TABLES as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropForeign(['tenant_id']);
                });
            }
        }

        app(LegacyTenantKeyRealignmentService::class)->realign();

        foreach (self::FK_TABLES as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Irreversible without a snapshot of previous tenant_id values.
    }
};
