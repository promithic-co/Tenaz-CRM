<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stress_test_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cpf_dataset_id')->nullable()->constrained('cpf_datasets')->nullOnDelete();
            $table->string('label');
            $table->text('objective');
            $table->json('config');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_cycles');
            $table->unsignedInteger('completed_cycles')->default(0);
            $table->json('results_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stress_test_runs');
    }
};
