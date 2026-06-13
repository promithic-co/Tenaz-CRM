<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_followup_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')
                ->unique()
                ->constrained('agents')
                ->cascadeOnDelete();
            $table->string('tenant_id');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('first_delay_minutes')->default(10);
            $table->unsignedSmallInteger('min_interval_minutes')->default(60);
            $table->unsignedTinyInteger('max_attempts_within_window')->default(2);
            $table->time('business_window_start')->default('08:00');
            $table->time('business_window_end')->default('20:00');
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->string('message_type', 40)->default('contextual');
            $table->string('tone', 40)->default('consultivo');
            $table->unsignedTinyInteger('persuasion_intensity')->default(2);
            $table->text('custom_instructions')->nullable();
            $table->timestamps();

            $table->index('tenant_id', 'idx_agent_followup_settings_tenant_id');
            $table->index(['tenant_id', 'enabled'], 'idx_agent_followup_settings_tenant_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_followup_settings');
    }
};
