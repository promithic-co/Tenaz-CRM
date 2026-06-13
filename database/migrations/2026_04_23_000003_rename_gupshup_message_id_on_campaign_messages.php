<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->renameColumn('gupshup_message_id', 'provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->renameColumn('provider_message_id', 'gupshup_message_id');
        });
    }
};
