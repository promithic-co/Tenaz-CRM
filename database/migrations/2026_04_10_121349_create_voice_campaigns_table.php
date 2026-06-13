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
        Schema::create('voice_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_instance_id')->constrained()->cascadeOnDelete();
            $table->text('greeting_template')->nullable();
            $table->text('post_call_message')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('delay_between_calls_ms')->default(3000);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('total_answered')->default(0);
            $table->unsignedInteger('total_interested')->default(0);
            $table->unsignedInteger('total_no_answer')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->text('failure_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_campaigns');
    }
};
