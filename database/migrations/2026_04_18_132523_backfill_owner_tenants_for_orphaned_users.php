<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find users with no tenant association and create an owner tenant for each
        $orphanedUsers = DB::table('users')
            ->whereNotIn('id', DB::table('tenant_user')->pluck('user_id'))
            ->get(['id', 'name']);

        foreach ($orphanedUsers as $user) {
            $userId = $user->id;
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => $user->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tenant_user')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Not reversible — removing created tenants could delete user data
    }
};
