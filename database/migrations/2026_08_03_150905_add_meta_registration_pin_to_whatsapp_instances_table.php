<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            // Cast `encrypted` on the model, so the column holds ciphertext and
            // needs the room a plain 6 character string would not. Stored at all
            // only because re-registering a number (token rotation, migration
            // between WABAs) otherwise means hunting down the PIN again.
            $table->text('meta_registration_pin')->nullable()->after('meta_access_token');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->dropColumn('meta_registration_pin');
        });
    }
};
