<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('pause_reason_code', 64)->nullable()->after('failure_reason');
            $table->string('paused_from_status', 32)->nullable()->after('pause_reason_code');
            $table->timestamp('risk_acknowledged_at')->nullable()->after('paused_from_status');
            $table->foreignId('risk_acknowledged_by')->nullable()->after('risk_acknowledged_at')->constrained('users')->nullOnDelete();

            $table->index(['tenant_id', 'pause_reason_code'], 'idx_campaigns_tenant_pause_reason');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex('idx_campaigns_tenant_pause_reason');
            $table->dropForeign(['risk_acknowledged_by']);
            $table->dropColumn([
                'pause_reason_code',
                'paused_from_status',
                'risk_acknowledged_at',
                'risk_acknowledged_by',
            ]);
        });
    }
};
