<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_list_entries', function (Blueprint $table): void {
            $table->index('phone', 'contact_list_entries_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contact_list_entries', function (Blueprint $table): void {
            $table->dropIndex('contact_list_entries_phone_idx');
        });
    }
};
