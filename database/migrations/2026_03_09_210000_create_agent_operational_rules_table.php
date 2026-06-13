<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_operational_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('instituicoes_config');   // bancos + produtos habilitados
            $table->json('regras_globais');         // limites mínimos numéricos
            $table->json('regras_especies');        // LOAS, Invalidez <60
            $table->timestamps();

            $table->unique('user_id'); // 1 linha por corretor
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_operational_rules');
    }
};
