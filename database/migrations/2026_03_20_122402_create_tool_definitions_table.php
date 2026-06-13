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
        Schema::create('tool_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->string('slug');
            $table->string('name');
            $table->text('description');
            $table->string('type')->default('webhook'); // webhook, internal, api
            $table->json('config')->nullable(); // {url, method, headers, timeout, response_mapping}
            $table->json('schema')->nullable(); // JSON Schema for parameters
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'agent_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_definitions');
    }
};
