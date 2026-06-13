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
        Schema::create('failed_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('error_tag');
            $table->string('error_source');
            $table->text('error_message');
            $table->json('context')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['error_tag']);
            $table->index(['lead_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_interactions');
    }
};
