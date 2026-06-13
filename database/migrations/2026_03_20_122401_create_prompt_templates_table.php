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
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->string('type'); // system, followup, bulk, evaluator
            $table->text('content');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('variables_schema')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
