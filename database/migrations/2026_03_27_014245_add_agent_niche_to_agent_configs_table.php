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
            $table->string('agent_niche', 30)->default('inss')->after('agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->dropColumn('agent_niche');
        });
    }
};
