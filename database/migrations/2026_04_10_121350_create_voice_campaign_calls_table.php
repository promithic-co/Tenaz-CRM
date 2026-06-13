<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('voice_campaign_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voice_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_list_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone');
            $table->string('contact_name')->nullable();
            $table->text('interpolated_message')->nullable();
            $table->string('call_sid')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['voice_campaign_id', 'status']);
            $table->index('call_sid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_campaign_calls');
    }
};
