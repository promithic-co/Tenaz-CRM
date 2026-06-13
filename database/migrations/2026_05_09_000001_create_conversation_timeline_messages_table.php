<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_timeline_messages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->string('direction', 20);
            $table->string('sender_type', 20);
            $table->string('channel', 20)->default('whatsapp');
            $table->text('body')->nullable();
            $table->json('media')->nullable();
            $table->string('status', 20);
            $table->string('source', 30);
            $table->string('interaction_id', 36)->nullable()->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
            $table->index(['tenant_id', 'lead_id', 'created_at'], 'timeline_tenant_lead_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_timeline_messages');
    }
};
