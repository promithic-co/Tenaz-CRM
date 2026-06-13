<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ura_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('whatsapp_template_id')
                ->nullable()
                ->constrained('whatsapp_templates')
                ->nullOnDelete();
            $table->string('name');
            $table->string('key_hash')->unique();
            $table->string('key_preview', 8);
            $table->boolean('active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ura_api_keys');
    }
};
