<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `is_hot` flag so tags can encode "strong signal" semantics
     * (D3 — Phase 47.1). Hot tags drive prioritization in Kanban (49)
     * and Smart Lists (51). Defaults to false; existing rows backfill
     * to false implicitly via column default.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->boolean('is_hot')->default(false)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropColumn('is_hot');
        });
    }
};
