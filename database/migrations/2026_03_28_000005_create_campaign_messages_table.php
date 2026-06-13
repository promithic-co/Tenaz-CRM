<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('contact_list_entry_id')->constrained('contact_list_entries')->cascadeOnDelete();
            $table->string('gupshup_message_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('template_params_resolved')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_list_entry_id']);
            $table->index(['gupshup_message_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_messages');
    }
};
