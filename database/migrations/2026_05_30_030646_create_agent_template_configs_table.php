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
        Schema::create('agent_template_configs', function (Blueprint $table) {
            $table->id();
            $table->string('template_slug', 100)->unique();
            $table->string('agent_provider', 30)->default('openai');
            $table->string('agent_model', 150)->default('gpt-4o-mini');
            $table->string('transcription_provider', 30)->default('openai');
            $table->string('transcription_model', 150)->default('whisper-1');
            $table->string('vision_provider', 30)->default('openai');
            $table->string('vision_model', 150)->default('gpt-4o');
            $table->decimal('temperature', 3, 2)->default(0.40);
            $table->unsignedSmallInteger('max_tokens')->default(1024);
            $table->unsignedSmallInteger('max_conversation_messages')->default(24);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_template_configs');
    }
};
