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
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->string('template_slug', 50)->nullable()->after('agent_niche');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->dropColumn('template_slug');
        });
    }
};
