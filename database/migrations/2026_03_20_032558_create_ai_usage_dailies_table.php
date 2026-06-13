<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_dailies', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('model')->default('unknown');
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedBigInteger('total_prompt_tokens')->default(0);
            $table->unsignedBigInteger('total_completion_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
            $table->timestamps();

            $table->unique(['date', 'tenant_id', 'agent_id', 'model'], 'ai_usage_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_dailies');
    }
};
