<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('lead_id')->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal')->after('status');
            $table->timestamp('sla_due_at')->nullable()->after('priority');
            $table->timestamp('claimed_at')->nullable()->after('sla_due_at');
            $table->timestamp('first_response_at')->nullable()->after('claimed_at');
            $table->timestamp('resolved_at')->nullable()->after('first_response_at');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
            $table->string('resolution_reason')->nullable()->after('closed_at');
            $table->text('resolution_notes')->nullable()->after('resolution_reason');
            $table->timestamp('last_customer_message_at')->nullable()->after('resolution_notes');
            $table->timestamp('last_operator_message_at')->nullable()->after('last_customer_message_at');
            $table->json('metadata')->nullable()->after('last_operator_message_at');

            $table->index(['tenant_id', 'status', 'priority', 'sla_due_at'], 'tickets_tenant_status_priority_sla_idx');
            $table->index(['tenant_id', 'assigned_user_id', 'status'], 'tickets_tenant_assignee_status_idx');
            $table->index(['tenant_id', 'lead_id', 'status'], 'tickets_tenant_lead_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_tenant_status_priority_sla_idx');
            $table->dropIndex('tickets_tenant_assignee_status_idx');
            $table->dropIndex('tickets_tenant_lead_status_idx');

            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn([
                'priority',
                'sla_due_at',
                'claimed_at',
                'first_response_at',
                'resolved_at',
                'closed_at',
                'resolution_reason',
                'resolution_notes',
                'last_customer_message_at',
                'last_operator_message_at',
                'metadata',
            ]);
        });
    }
};
