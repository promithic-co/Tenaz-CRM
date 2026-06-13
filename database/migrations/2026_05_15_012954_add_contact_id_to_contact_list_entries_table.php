<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_list_entries', function (Blueprint $table): void {
            $table->foreignId('contact_id')
                ->nullable()
                ->after('lead_id')
                ->constrained('contacts')
                ->nullOnDelete();

            $table->index(['contact_id']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_list_entries', function (Blueprint $table): void {
            $table->dropIndex(['contact_id']);
            $table->dropConstrainedForeignId('contact_id');
        });
    }
};
