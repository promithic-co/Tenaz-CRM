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
        Schema::create('prompt_experiments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('prompt_type');
            $table->json('variants');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'prompt_type', 'is_active']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('experiment_slug')->nullable()->after('status');
            $table->string('experiment_variant')->nullable()->after('experiment_slug');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['experiment_slug', 'experiment_variant']);
        });

        Schema::dropIfExists('prompt_experiments');
    }
};
