<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->timestamp('service_window_expires_at')->nullable()->after('last_inbound_at');
            $table->timestamp('free_entry_point_started_at')->nullable()->after('service_window_expires_at');
            $table->timestamp('free_entry_point_expires_at')->nullable()->after('free_entry_point_started_at');
            $table->string('conversation_window_source', 64)->nullable()->after('free_entry_point_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn([
                'service_window_expires_at',
                'free_entry_point_started_at',
                'free_entry_point_expires_at',
                'conversation_window_source',
            ]);
        });
    }
};
