<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->dropColumn('gupshup_app_name');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->string('gupshup_app_name')->nullable()->after('api_key');
        });
    }
};
