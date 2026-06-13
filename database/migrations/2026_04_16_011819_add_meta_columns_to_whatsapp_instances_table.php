<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->string('meta_phone_number_id', 64)->nullable()->after('gupshup_app_name');
            $table->string('meta_waba_id', 64)->nullable()->after('meta_phone_number_id');
            $table->text('meta_access_token')->nullable()->after('meta_waba_id');
            $table->string('meta_system_user_id', 64)->nullable()->after('meta_access_token');
            $table->string('meta_quality_rating', 16)->nullable()->after('meta_system_user_id');
            $table->index('meta_phone_number_id', 'idx_whatsapp_instances_meta_phone_number_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->dropIndex('idx_whatsapp_instances_meta_phone_number_id');
            $table->dropColumn(['meta_phone_number_id', 'meta_waba_id', 'meta_access_token', 'meta_system_user_id', 'meta_quality_rating']);
        });
    }
};
