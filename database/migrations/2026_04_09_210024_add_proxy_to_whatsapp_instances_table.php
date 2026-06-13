<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->string('proxy_host')->nullable()->after('provider');
            $table->string('proxy_port', 10)->nullable()->after('proxy_host');
            $table->string('proxy_protocol', 10)->nullable()->default('http')->after('proxy_port');
            $table->string('proxy_username')->nullable()->after('proxy_protocol');
            $table->string('proxy_password')->nullable()->after('proxy_username');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->dropColumn(['proxy_host', 'proxy_port', 'proxy_protocol', 'proxy_username', 'proxy_password']);
        });
    }
};
