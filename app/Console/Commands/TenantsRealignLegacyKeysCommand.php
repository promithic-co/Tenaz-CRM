<?php

namespace App\Console\Commands;

use App\Services\LegacyTenantKeyRealignmentService;
use Illuminate\Console\Command;

/**
 * After introducing `tenants` + `tenant_user`, the app resolves the active tenant to
 * {@see \App\Models\Tenant::$id}. Older rows still store `tenant_id` as the owner's user id string.
 * This command rewrites those keys to the real tenant id (owner membership) — same rows, in place.
 *
 * @see LegacyTenantKeyRealignmentService Deploy also runs this automatically via migration.
 */
class TenantsRealignLegacyKeysCommand extends Command
{
    protected $signature = 'tenants:realign-legacy-keys
                            {--dry-run : List updates without writing}
                            {--user= : Process only this user id}';

    protected $description = 'Rewrite legacy tenant_id keys (user id string) to the canonical tenants.id for each user\'s primary tenant.';

    public function handle(LegacyTenantKeyRealignmentService $realignment): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyUserId = $this->option('user') !== null ? (int) $this->option('user') : null;

        $result = $realignment->realign(
            $onlyUserId,
            $dryRun,
            function (string $email, string $legacyKey, string $canonicalKey): void {
                $this->line("User ({$email}): <comment>{$legacyKey}</comment> → <info>{$canonicalKey}</info>");
            }
        );

        if ($dryRun) {
            $this->info("Dry run: {$result['rows_updated']} row(s) would be updated across {$result['users_matched']} user(s). Run without --dry-run to apply.");
        } else {
            $this->info("Done. {$result['rows_updated']} row(s) updated for {$result['users_matched']} user(s). Consider: php artisan cache:clear");
        }

        return self::SUCCESS;
    }
}
