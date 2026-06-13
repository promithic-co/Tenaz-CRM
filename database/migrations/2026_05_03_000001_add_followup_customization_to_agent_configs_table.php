<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->string('followup_message_type', 40)->default('reengajamento')->after('followup_interval_days');
            $table->string('followup_tone', 40)->default('consultivo')->after('followup_message_type');
            $table->unsignedTinyInteger('followup_persuasion_intensity')->default(2)->after('followup_tone');
            $table->text('followup_custom_instructions')->nullable()->after('followup_persuasion_intensity');
        });
    }

    public function down(): void
    {
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->dropColumn([
                'followup_message_type',
                'followup_tone',
                'followup_persuasion_intensity',
                'followup_custom_instructions',
            ]);
        });
    }
};
