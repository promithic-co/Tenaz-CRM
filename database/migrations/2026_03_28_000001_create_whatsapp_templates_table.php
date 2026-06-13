<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('whatsapp_instance_id')->nullable()->constrained('whatsapp_instances')->nullOnDelete();
            $table->string('gupshup_element_name');
            $table->string('name');
            $table->string('status')->default('pending');
            $table->string('category')->nullable();
            $table->string('language')->default('pt_BR');
            $table->text('body');
            $table->text('header')->nullable();
            $table->string('footer')->nullable();
            $table->json('buttons_json')->nullable();
            $table->unsignedTinyInteger('variables_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'whatsapp_instance_id', 'gupshup_element_name']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
