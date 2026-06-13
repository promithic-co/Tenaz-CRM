<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('name')->nullable();
            $table->string('phone'); // normalized E.164 digits (no +)
            $table->string('email')->nullable();
            $table->string('cpf', 14)->nullable();
            $table->string('source')->default('manual'); // manual, lead_sync, csv_import, whatsapp_inbound, ura, agent_api
            $table->string('opt_in_status')->default('pending'); // pending, opted_in, opted_out
            $table->timestamp('opt_in_at')->nullable();
            $table->timestamp('opt_out_at')->nullable();
            $table->json('extra_data')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'phone'], 'contacts_tenant_phone_unique');
            $table->index(['tenant_id', 'opt_in_status']);
            $table->index(['tenant_id', 'source']);
            $table->index(['tenant_id', 'cpf']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
