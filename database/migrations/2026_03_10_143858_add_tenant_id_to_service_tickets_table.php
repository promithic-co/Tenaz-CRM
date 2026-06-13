<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->string('tenant_id')->default('default')->after('id')->index();
        });

        // Backfill tenant_id from the associated lead
        DB::statement('UPDATE service_tickets SET tenant_id = (SELECT tenant_id FROM leads WHERE leads.id = service_tickets.lead_id)');
    }

    public function down(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
