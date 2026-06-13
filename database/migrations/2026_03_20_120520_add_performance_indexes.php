<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'leads_tenant_status_idx');
            $table->index(['tenant_id', 'followup_status'], 'leads_tenant_followup_idx');
            $table->index(['tenant_id', 'created_at'], 'leads_tenant_created_idx');
        });

        Schema::table('service_tickets', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'tickets_tenant_status_created_idx');
        });

        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at'], 'messages_conv_created_idx');
        });

        Schema::table('followup_messages', function (Blueprint $table) {
            $table->index(['lead_id', 'sent_at'], 'followup_lead_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_tenant_status_idx');
            $table->dropIndex('leads_tenant_followup_idx');
            $table->dropIndex('leads_tenant_created_idx');
        });

        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_tenant_status_created_idx');
        });

        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->dropIndex('messages_conv_created_idx');
        });

        Schema::table('followup_messages', function (Blueprint $table) {
            $table->dropIndex('followup_lead_sent_idx');
        });
    }
};
