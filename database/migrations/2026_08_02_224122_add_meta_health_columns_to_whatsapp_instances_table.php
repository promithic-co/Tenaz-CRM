<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->string('meta_health_status', 16)->nullable()->after('meta_coexistence');
            $table->json('meta_health_entities')->nullable()->after('meta_health_status');
            $table->string('meta_name_status', 32)->nullable()->after('meta_health_entities');
            $table->string('meta_code_verification_status', 32)->nullable()->after('meta_name_status');
            $table->string('meta_portfolio_messaging_limit', 32)->nullable()->after('meta_code_verification_status');
            $table->string('meta_throughput_level', 32)->nullable()->after('meta_portfolio_messaging_limit');
            $table->string('meta_number_status', 32)->nullable()->after('meta_throughput_level');
            $table->json('meta_account_restrictions')->nullable()->after('meta_number_status');
            $table->string('meta_ban_state', 32)->nullable()->after('meta_account_restrictions');
            $table->string('meta_account_review_status', 32)->nullable()->after('meta_ban_state');
            $table->timestamp('meta_health_checked_at')->nullable()->after('meta_account_review_status');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->dropColumn([
                'meta_health_status',
                'meta_health_entities',
                'meta_name_status',
                'meta_code_verification_status',
                'meta_portfolio_messaging_limit',
                'meta_throughput_level',
                'meta_number_status',
                'meta_account_restrictions',
                'meta_ban_state',
                'meta_account_review_status',
                'meta_health_checked_at',
            ]);
        });
    }
};
