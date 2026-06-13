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
        Schema::create('status_machines', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->string('entity_type')->default('lead');
            $table->json('statuses'); // [{slug, label, color, is_terminal}]
            $table->json('transitions'); // [{from, to}]
            $table->string('initial_status')->default('novo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_machines');
    }
};
