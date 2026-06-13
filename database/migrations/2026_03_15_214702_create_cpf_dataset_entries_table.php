<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpf_dataset_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpf_dataset_id')->constrained('cpf_datasets')->cascadeOnDelete();
            $table->string('cpf', 11);
            $table->string('nome');
            $table->string('status_expected');
            $table->json('qualified_json')->nullable();
            $table->json('promosys_raw')->nullable();
            $table->timestamps();

            $table->index('cpf_dataset_id');
            $table->index('cpf');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpf_dataset_entries');
    }
};
