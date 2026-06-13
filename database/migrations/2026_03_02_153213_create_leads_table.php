<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->default('default')->index();
            $table->string('whatsapp');
            $table->string('nome')->nullable();
            $table->string('cpf')->nullable();
            $table->unsignedSmallInteger('idade')->nullable();
            $table->string('status')->default('novo');
            $table->string('modo')->default('receptivo');
            $table->json('credito_json')->nullable();
            $table->json('documentos_coletados')->nullable();
            $table->string('conversation_id', 36)->nullable();
            $table->integer('followup_count')->default(0);
            $table->string('followup_status', 20)->default('inactive');
            $table->timestamp('last_interaction_at')->nullable();
            $table->string('evolution_instance', 50)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'whatsapp']);
            $table->index(['followup_status', 'last_interaction_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
