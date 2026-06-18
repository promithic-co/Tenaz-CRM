<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->uuid('trace_id')->nullable()->index();
            $table->string('tenant_id')->index();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('agent_name')->nullable();
            $table->string('architecture_version', 40)->default('legacy_prompt')->index();
            $table->string('prompt_hash', 64)->nullable()->index();
            $table->string('skill_hash', 64)->nullable()->index();
            $table->string('model')->nullable()->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('llm_calls')->default(0);
            $table->unsignedSmallInteger('tool_calls')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
            $table->string('status', 30)->default('success')->index();
            $table->string('outcome', 40)->nullable()->index();
            $table->string('error_type', 80)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'started_at'], 'ai_runs_tenant_started_idx');
            $table->index(['tenant_id', 'architecture_version', 'started_at'], 'ai_runs_tenant_arch_started_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
