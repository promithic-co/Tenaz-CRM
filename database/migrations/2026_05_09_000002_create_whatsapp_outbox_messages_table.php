<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('provider', 30)->nullable();
            $table->json('payload_json');
            $table->string('status', 20)->default('queued')->index();
            $table->string('idempotency_key')->unique();
            $table->string('provider_message_id')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('timeline_message_id')->nullable()->constrained('conversation_timeline_messages')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('interaction_id', 36)->nullable()->index();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_outbox_messages');
    }
};
