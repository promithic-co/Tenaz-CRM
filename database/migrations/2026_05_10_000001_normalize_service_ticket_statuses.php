<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_tickets')
            ->where('status', 'aberto')
            ->update(['status' => 'open']);

        DB::table('service_tickets')
            ->where('status', 'resolvido')
            ->update(['status' => 'resolved']);

        DB::table('service_tickets')
            ->where('status', 'fechado')
            ->update(['status' => 'closed']);
    }

    public function down(): void
    {
        DB::table('service_tickets')
            ->where('status', 'open')
            ->update(['status' => 'aberto']);

        DB::table('service_tickets')
            ->where('status', 'resolved')
            ->update(['status' => 'resolvido']);

        DB::table('service_tickets')
            ->where('status', 'closed')
            ->update(['status' => 'fechado']);
    }
};
