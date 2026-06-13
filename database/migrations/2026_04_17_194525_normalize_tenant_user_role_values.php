<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenant_user')->where('role', 'member')->update(['role' => 'user']);

        DB::table('tenant_user')
            ->whereNotIn('role', ['owner', 'administrator', 'user'])
            ->update(['role' => 'user']);
    }

    public function down(): void
    {
        DB::table('tenant_user')->where('role', 'user')->update(['role' => 'member']);
    }
};
