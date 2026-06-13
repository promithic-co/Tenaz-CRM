<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->boolean('meta_coexistence')->default(false)->after('meta_quality_rating');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->dropColumn('meta_coexistence');
        });
    }
};
