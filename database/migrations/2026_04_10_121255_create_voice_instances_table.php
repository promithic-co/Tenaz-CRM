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
        Schema::create('voice_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('twilio_account_sid');
            $table->string('twilio_auth_token');
            $table->string('twilio_phone_number');
            $table->foreignId('whatsapp_instance_id')->nullable()->constrained('whatsapp_instances')->nullOnDelete();
            $table->text('greeting_template')->nullable();
            $table->text('post_call_message')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_instances');
    }
};
