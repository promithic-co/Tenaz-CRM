<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->string('error_subcode')->nullable()->after('error_code');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_messages', function (Blueprint $table): void {
            $table->dropColumn('error_subcode');
        });
    }
};
