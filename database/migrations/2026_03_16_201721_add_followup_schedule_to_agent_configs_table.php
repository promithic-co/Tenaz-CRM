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
            $table->time('followup_window_start')->default('08:00')->after('followup_approach');
            $table->time('followup_window_end')->default('20:00')->after('followup_window_start');
            $table->unsignedTinyInteger('followup_interval_days')->default(1)->after('followup_window_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->dropColumn(['followup_window_start', 'followup_window_end', 'followup_interval_days']);
        });
    }
};
