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
        Schema::table('taggables', function (Blueprint $table): void {
            $table->decimal('ai_confidence', 3, 2)->nullable()->after('tagged_by');
            $table->string('ai_evidence', 180)->nullable()->after('ai_confidence');
            $table->timestamp('ai_evaluated_at')->nullable()->after('ai_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('taggables', function (Blueprint $table): void {
            $table->dropColumn(['ai_confidence', 'ai_evidence', 'ai_evaluated_at']);
        });
    }
};
