<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->index(['followup_status', 'last_inbound_at'], 'leads_followup_inbound_idx');
            $table->index(['tenant_id', 'agent_id', 'status'], 'leads_tenant_agent_status_idx');
        });

        Schema::table('agent_conversation_messages', function (Blueprint $table): void {
            $table->index(['conversation_id', 'created_at'], 'acm_conversation_created_idx');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->index(['status', 'scheduled_at'], 'campaigns_status_scheduled_idx');
        });

        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->index(['contact_list_entry_id'], 'campaign_messages_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_followup_inbound_idx');
            $table->dropIndex('leads_tenant_agent_status_idx');
        });

        Schema::table('agent_conversation_messages', function (Blueprint $table): void {
            $table->dropIndex('acm_conversation_created_idx');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex('campaigns_status_scheduled_idx');
        });

        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->dropIndex('campaign_messages_entry_idx');
        });
    }
};
