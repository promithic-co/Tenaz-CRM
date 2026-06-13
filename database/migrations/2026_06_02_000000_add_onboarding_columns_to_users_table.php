<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable();
            $table->foreignId('onboarding_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->timestamp('onboarding_whatsapp_skipped_at')->nullable();
        });

        // Backfill: mark owners as onboarded if their tenant has at least one agent.
        // Uses table-level queries only — no Eloquent scopes, no HTTP tenant context.
        // Matches agents by tenant_id (not user_id): the tenant may have agents
        // assigned to other members, but the owner is still considered complete (D-22).
        $ownerUserIds = DB::table('tenant_user')
            ->where('role', \App\Enums\TenantRole::Owner->value)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('agents')
                    ->whereColumn('agents.tenant_id', 'tenant_user.tenant_id')
                    ->whereNull('agents.deleted_at');
            })
            ->pluck('user_id')->unique()->all();

        if (! empty($ownerUserIds)) {
            DB::table('users')->whereIn('id', $ownerUserIds)->update(['onboarded_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['onboarding_agent_id']);
            $table->dropColumn('onboarding_agent_id');
            $table->dropColumn('onboarded_at');
            $table->dropColumn('onboarding_whatsapp_skipped_at');
        });
    }
};
