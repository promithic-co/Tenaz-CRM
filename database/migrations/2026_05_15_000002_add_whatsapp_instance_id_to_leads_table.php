<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 46 - introduce a semantic FK from leads to whatsapp_instances.
     * The legacy `evolution_instance` string column predates the multi-provider
     * abstraction (Meta Cloud, Evolution, ...) and conflates "channel id"
     * with provider-specific naming. We keep it for backwards compatibility
     * during rollout and backfill the new FK using (tenant_id, name).
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('whatsapp_instance_id')
                ->nullable()
                ->after('evolution_instance')
                ->constrained('whatsapp_instances')
                ->nullOnDelete();
            $table->index(['tenant_id', 'whatsapp_instance_id'], 'leads_tenant_instance_idx');
        });

        // Backfill from existing evolution_instance string by matching on
        // (tenant_id, name). Skip rows that cannot be resolved unambiguously.
        DB::table('leads')
            ->whereNull('whatsapp_instance_id')
            ->whereNotNull('evolution_instance')
            ->orderBy('id')
            ->chunkById(500, function ($leads): void {
                foreach ($leads as $lead) {
                    $instanceId = DB::table('whatsapp_instances')
                        ->where('tenant_id', $lead->tenant_id)
                        ->where('name', $lead->evolution_instance)
                        ->value('id');

                    if ($instanceId !== null) {
                        DB::table('leads')
                            ->where('id', $lead->id)
                            ->update(['whatsapp_instance_id' => $instanceId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_tenant_instance_idx');
            $table->dropConstrainedForeignId('whatsapp_instance_id');
        });
    }
};
