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
        Schema::table('tags', function (Blueprint $table): void {
            $table->boolean('ai_detectable')->default(false)->after('is_hot');
            $table->text('ai_description')->nullable()->after('ai_detectable');
            $table->decimal('ai_min_confidence', 3, 2)->default(0.70)->after('ai_description');
            $table->index('ai_detectable');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropIndex(['ai_detectable']);
            $table->dropColumn(['ai_detectable', 'ai_description', 'ai_min_confidence']);
        });
    }
};
