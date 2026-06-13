<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('contact_id')
                ->nullable()
                ->after('campaign_id')
                ->constrained('contacts')
                ->nullOnDelete();

            $table->index(['tenant_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'contact_id']);
            $table->dropConstrainedForeignId('contact_id');
        });
    }
};
