<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stress_test_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stress_test_run_id')->constrained('stress_test_runs')->cascadeOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->string('cpf_used', 11)->nullable();
            $table->text('scenario')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('fidelity_score', 5, 2)->nullable();
            $table->json('hallucinations')->nullable();
            $table->json('token_metrics')->nullable();
            $table->text('evaluation_report')->nullable();
            $table->json('console_errors')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stress_test_cycles');
    }
};
