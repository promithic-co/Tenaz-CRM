<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_lists', function (Blueprint $table): void {
            $table->boolean('is_dynamic')->default(false)->after('source');
            $table->json('filters_json')->nullable()->after('is_dynamic');
            $table->unsignedInteger('last_resolved_count')->nullable()->after('entries_count');
            $table->timestamp('last_resolved_at')->nullable()->after('last_resolved_count');
        });
    }

    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table): void {
            $table->dropColumn(['is_dynamic', 'filters_json', 'last_resolved_count', 'last_resolved_at']);
        });
    }
};
