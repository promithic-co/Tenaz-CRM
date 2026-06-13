<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('whatsapp_instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->foreignId('contact_list_id')->constrained('contact_lists')->cascadeOnDelete();
            $table->foreignId('whatsapp_template_id')->constrained('whatsapp_templates')->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->json('template_params_mapping')->nullable();
            $table->unsignedInteger('daily_limit')->default(1000);
            $table->unsignedInteger('delay_between_ms')->default(1000);
            $table->unsignedTinyInteger('error_threshold_percent')->default(10);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('total_sent')->default(0);
            $table->unsignedInteger('total_delivered')->default(0);
            $table->unsignedInteger('total_read')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
