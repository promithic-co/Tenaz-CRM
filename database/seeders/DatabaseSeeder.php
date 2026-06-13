<?php

namespace Database\Seeders;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    private const DEFAULT_ADMIN_NAME = 'Admin';

    private const DEFAULT_ADMIN_EMAIL = 'admin@admin.com';

    private const DEFAULT_ADMIN_PASSWORD = 'admin';

    /**
     * Seed the application's database.
     *
     * Creates or updates the local admin user from environment variables.
     * Run with: php artisan db:seed
     *
     * Optional env vars:
     *   SEED_ADMIN_NAME, SEED_ADMIN_EMAIL, SEED_ADMIN_PASSWORD
     */
    public function run(): void
    {
        $this->call(AgentTemplateConfigSeeder::class);

        if (! app()->environment('local', 'testing')) {
            $this->command->warn('Skipping local admin seeder outside the local environment.');

            return;
        }

        $name = trim((string) env('SEED_ADMIN_NAME', self::DEFAULT_ADMIN_NAME));
        $email = strtolower(trim((string) env('SEED_ADMIN_EMAIL', self::DEFAULT_ADMIN_EMAIL)));
        $password = (string) env('SEED_ADMIN_PASSWORD', self::DEFAULT_ADMIN_PASSWORD);

        $name = $name !== '' ? $name : self::DEFAULT_ADMIN_NAME;
        $email = $email !== '' ? $email : self::DEFAULT_ADMIN_EMAIL;
        $password = $password !== '' ? $password : self::DEFAULT_ADMIN_PASSWORD;

        $user = DB::transaction(function () use ($name, $email, $password): User {
            $user = User::query()->where('email', $email)->first();

            if ($user) {
                $user->forceFill([
                    'name' => $name,
                    'password' => Hash::make($password),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                ]);

                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $tenant = $user->tenants()->first()
                ?? Tenant::query()->first()
                ?? Tenant::create(['name' => $name]);

            $user->tenants()->syncWithoutDetaching([
                $tenant->id => ['role' => TenantRole::Owner->value],
            ]);

            $user->tenants()->updateExistingPivot($tenant->id, [
                'role' => TenantRole::Owner->value,
            ]);

            return $user;
        });

        $this->command->info("Local admin ready: {$user->email} (ID: {$user->id})");
    }
}
