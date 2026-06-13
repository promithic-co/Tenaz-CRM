<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_campaigns', function (Blueprint $table) {
            // Which TTS voice the robot uses (Twilio voice identifier)
            $table->string('tts_voice')->default('Google.pt-BR-Standard-A')->after('greeting_template');

            // JSON map of DTMF digit => { action, label }
            // e.g. {"1":{"action":"interested","label":"Tenho interesse"},"2":{"action":"optout","label":"Não quero mais ligar"}}
            $table->json('dtmf_actions')->nullable()->after('tts_voice');
        });
    }

    public function down(): void
    {
        Schema::table('voice_campaigns', function (Blueprint $table) {
            $table->dropColumn(['tts_voice', 'dtmf_actions']);
        });
    }
};
