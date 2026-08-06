<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->string('header_media_id')->nullable();
            $table->string('header_media_mime_type', 100)->nullable();
            $table->string('header_media_filename')->nullable();
            $table->unsignedBigInteger('header_media_size_bytes')->nullable();
            $table->timestamp('header_media_uploaded_at')->nullable();
            $table->timestamp('header_media_expires_at')->nullable()->index();
            $table->binary('header_media_preview')->nullable();
            $table->string('header_media_preview_mime_type', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropIndex(['header_media_expires_at']);
            $table->dropColumn([
                'header_media_id',
                'header_media_mime_type',
                'header_media_filename',
                'header_media_size_bytes',
                'header_media_uploaded_at',
                'header_media_expires_at',
                'header_media_preview',
                'header_media_preview_mime_type',
            ]);
        });
    }
};
