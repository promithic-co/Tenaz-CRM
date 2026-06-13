<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_instances', function (Blueprint $table): void {
            $table->foreignId('post_call_meta_template_id')
                ->nullable()
                ->after('whatsapp_instance_id')
                ->constrained('whatsapp_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('voice_instances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('post_call_meta_template_id');
        });
    }
};
