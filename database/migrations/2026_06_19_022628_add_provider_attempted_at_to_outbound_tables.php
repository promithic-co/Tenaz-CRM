<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_outbox_messages', function (Blueprint $table): void {
            $table->timestamp('provider_attempted_at')->nullable()->after('attempts');
        });

        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->timestamp('provider_attempted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_outbox_messages', function (Blueprint $table): void {
            $table->dropColumn('provider_attempted_at');
        });

        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->dropColumn('provider_attempted_at');
        });
    }
};
