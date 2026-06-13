<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_list_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_list_id')->constrained('contact_lists')->cascadeOnDelete();
            $table->string('phone');
            $table->string('name')->nullable();
            $table->string('opt_in_status')->default('pending');
            $table->timestamp('opt_in_at')->nullable();
            $table->timestamp('opt_out_at')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->json('extra_data')->nullable();
            $table->timestamps();

            $table->unique(['contact_list_id', 'phone']);
            $table->index(['contact_list_id', 'opt_in_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_list_entries');
    }
};
