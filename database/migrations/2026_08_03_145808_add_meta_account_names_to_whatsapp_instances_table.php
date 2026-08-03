<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->string('meta_waba_name')->nullable()->after('meta_waba_id');
            $table->string('meta_business_name')->nullable()->after('meta_waba_name');
            $table->string('meta_verified_name')->nullable()->after('meta_business_name');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->dropColumn([
                'meta_waba_name',
                'meta_business_name',
                'meta_verified_name',
            ]);
        });
    }
};
