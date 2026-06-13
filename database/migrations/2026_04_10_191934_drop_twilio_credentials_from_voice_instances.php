<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('voice_instances', function (Blueprint $table) {
            $table->dropColumn(['twilio_account_sid', 'twilio_auth_token', 'twilio_phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voice_instances', function (Blueprint $table) {
            $table->string('twilio_account_sid')->after('display_name');
            $table->string('twilio_auth_token')->after('twilio_account_sid');
            $table->string('twilio_phone_number')->after('twilio_auth_token');
        });
    }
};
