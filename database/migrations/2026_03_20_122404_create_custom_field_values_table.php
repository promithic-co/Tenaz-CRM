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
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('custom_field_id');
            $table->string('entity_type')->default('lead');
            $table->unsignedBigInteger('entity_id');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 15, 4)->nullable();
            $table->json('value_json')->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_id', 'entity_type', 'entity_id'], 'custom_field_values_unique');
            $table->foreign('custom_field_id')->references('id')->on('custom_fields')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
