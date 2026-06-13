<?php

use App\Models\ServiceTicket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->string('default_ai_mode', 30)->default('automatic')->after('agent_id')->index();
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->string('ai_mode', 30)->nullable()->after('modo')->index();
            $table->string('operational_stage', 40)->default('new_inbound')->after('ai_mode')->index();
            $table->foreignId('assigned_user_id')->nullable()->after('operational_stage')->constrained('users')->nullOnDelete();
            $table->timestamp('ai_paused_until')->nullable()->after('assigned_user_id')->index();
            $table->string('ai_paused_reason', 80)->nullable()->after('ai_paused_until');
            $table->foreignId('ai_paused_by')->nullable()->after('ai_paused_reason')->constrained('users')->nullOnDelete();

            $table->index(['tenant_id', 'operational_stage', 'last_interaction_at'], 'leads_tenant_stage_interaction_idx');
            $table->index(['tenant_id', 'assigned_user_id', 'operational_stage'], 'leads_tenant_assignee_stage_idx');
        });

        DB::table('leads')->orderBy('id')->chunkById(500, function ($leads): void {
            foreach ($leads as $lead) {
                DB::table('leads')
                    ->where('id', $lead->id)
                    ->update([
                        'operational_stage' => $this->stageForLead($lead),
                    ]);
            }
        });

        DB::table('service_tickets')
            ->whereIn('status', ServiceTicket::ACTIVE_STATUSES)
            ->whereNotNull('assigned_user_id')
            ->orderBy('id')
            ->chunkById(500, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    DB::table('leads')
                        ->where('id', $ticket->lead_id)
                        ->whereNull('assigned_user_id')
                        ->update(['assigned_user_id' => $ticket->assigned_user_id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_tenant_stage_interaction_idx');
            $table->dropIndex('leads_tenant_assignee_stage_idx');
            $table->dropConstrainedForeignId('ai_paused_by');
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn([
                'ai_mode',
                'operational_stage',
                'ai_paused_until',
                'ai_paused_reason',
            ]);
        });

        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->dropIndex(['default_ai_mode']);
            $table->dropColumn('default_ai_mode');
        });
    }

    private function stageForLead(object $lead): string
    {
        if ($lead->status === 'convertido') {
            return 'won';
        }

        if (in_array($lead->status, ['desqualificado', 'optou_sair'], true)) {
            return 'lost';
        }

        if ($lead->status === 'sem_credito') {
            return 'future_opportunity';
        }

        if ($lead->status === 'escalado') {
            return 'human_pending';
        }

        if ($lead->followup_status === 'active') {
            return 'ai_followup';
        }

        if ($lead->status === 'qualificado') {
            return 'qualified_opportunity';
        }

        return 'new_inbound';
    }
};
