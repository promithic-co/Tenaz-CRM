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
        Schema::create('agent_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete()->unique();
            $table->string('agent_name', 50)->default('ARIA');
            $table->string('company_name', 100)->default('Amec');
            $table->string('agent_personality', 200)->default('direta, acolhedora e profissional');
            $table->unsignedSmallInteger('max_chars')->default(300);
            $table->string('agent_greeting', 300)->default('Cumprimente pelo nome e apresente-se como consultora da empresa');
            $table->string('required_docs', 500)->default('RG/CNH, comprovante de residência, dados bancários (banco/agência/conta)');
            $table->text('extra_rules')->nullable();
            $table->string('agent_provider', 20)->default('openai');
            $table->string('agent_model', 150)->default('gpt-4o-mini');
            $table->string('transcription_provider', 20)->default('openai');
            $table->string('transcription_model', 150)->default('whisper-1');
            $table->string('vision_provider', 20)->default('openai');
            $table->string('vision_model', 150)->default('gpt-4o');
            $table->string('escalation_whatsapp_number', 20)->default('');
            $table->decimal('temperature', 3, 2)->default(0.40);
            $table->unsignedSmallInteger('max_tokens')->default(1024);
            $table->unsignedTinyInteger('max_conversation_messages')->default(24);
            $table->unsignedSmallInteger('followup_first_delay_minutes')->default(10);
            $table->string('followup_daily_time', 5)->default('10:00');
            $table->unsignedTinyInteger('followup_max_count')->default(4);
            $table->string('followup_approach', 20)->default('natural');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_configs');
    }
};
