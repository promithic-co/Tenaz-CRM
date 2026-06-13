<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->index()->after('evolution_instance');
            $table->string('sandbox_label', 100)->nullable()->after('is_sandbox');
            $table->text('sandbox_system_prompt')->nullable()->after('sandbox_label');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['is_sandbox', 'sandbox_label', 'sandbox_system_prompt']);
        });
    }
};
