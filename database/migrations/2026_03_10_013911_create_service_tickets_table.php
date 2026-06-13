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
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // escalation, no_credit
            $table->string('status')->default('open'); // open, resolved, closed
            $table->string('reason')->nullable();
            $table->text('summary')->nullable();
            $table->string('credit_available')->nullable();
            $table->string('chosen_product')->nullable();
            $table->decimal('total_value', 10, 2)->nullable();
            $table->decimal('installment_value', 10, 2)->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
    }
};
