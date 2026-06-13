<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->json('components_json')->nullable()->after('buttons_json');
            $table->string('quality_score', 32)->nullable()->after('components_json');
            $table->string('rejected_reason')->nullable()->after('quality_score');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropColumn(['components_json', 'quality_score', 'rejected_reason']);
        });
    }
};
