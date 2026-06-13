<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->boolean('meta_token_permanent')->default(true)->after('meta_system_user_id');
            $table->timestamp('meta_token_expires_at')->nullable()->after('meta_token_permanent');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->dropColumn(['meta_token_permanent', 'meta_token_expires_at']);
        });
    }
};
