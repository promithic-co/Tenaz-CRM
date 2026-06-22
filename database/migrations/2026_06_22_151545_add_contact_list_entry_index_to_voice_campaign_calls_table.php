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
        Schema::table('voice_campaign_calls', function (Blueprint $table) {
            $table->index(['voice_campaign_id', 'contact_list_entry_id'], 'vcc_campaign_entry_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voice_campaign_calls', function (Blueprint $table) {
            $table->dropIndex('vcc_campaign_entry_idx');
        });
    }
};
