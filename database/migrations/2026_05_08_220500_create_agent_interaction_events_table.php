<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_interaction_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('interaction_id');
            $table->string('tenant_id')->index();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('event_source', 80);
            $table->string('severity', 20)->default('info');
            $table->json('payload_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['interaction_id', 'created_at'], 'agent_interaction_events_trace_idx');
            $table->index(['tenant_id', 'created_at'], 'agent_interaction_events_tenant_time_idx');
            $table->index(['event_type', 'created_at'], 'agent_interaction_events_type_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_interaction_events');
    }
};
