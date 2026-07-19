<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_timeline_messages', function (Blueprint $table): void {
            $table->json('metadata')->nullable()->after('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_timeline_messages', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
    }
};
