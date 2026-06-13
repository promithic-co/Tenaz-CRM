<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use Illuminate\Console\Command;

class ManageSettingCommand extends Command
{
    protected $signature = 'credflow:setting
                            {key? : Setting key to read or update}
                            {value? : New value to set (omit to read current value)}
                            {--user= : User ID scope (omit for global)}
                            {--all : List all current settings}
                            {--reset-cache : Invalidate agent config cache only}';

    protected $description = 'Read or update application settings without rebuilding.';

    public function handle(): int
    {
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;

        if ($this->option('reset-cache')) {
            AppSetting::invalidateAgentConfigCache($userId);
            $this->info('Agent config cache invalidated'.($userId ? " for user {$userId}" : ' globally').'.');

            return Command::SUCCESS;
        }

        if ($this->option('all')) {
            return $this->listAll($userId);
        }

        $key = $this->argument('key');

        if (! $key) {
            $this->error('Provide a key, or use --all to list all settings.');

            return Command::FAILURE;
        }

        if ($this->argument('value') !== null) {
            return $this->updateSetting($key, $this->argument('value'), $userId);
        }

        return $this->readSetting($key, $userId);
    }

    private function readSetting(string $key, ?int $userId): int
    {
        $value = AppSetting::get($key, null, $userId);

        if ($value === null) {
            $this->warn("Setting '{$key}' not found in database (default will be used).");
        } else {
            $this->line("<info>{$key}</info> = {$value}".($userId ? " (user {$userId})" : ' (global)'));
        }

        return Command::SUCCESS;
    }

    private function updateSetting(string $key, string $value, ?int $userId): int
    {
        AppSetting::set($key, $value, $userId);

        $this->info("Updated '{$key}'".($userId ? " for user {$userId}" : ' globally').'.');
        $this->line("New value: {$value}");

        return Command::SUCCESS;
    }

    private function listAll(?int $userId): int
    {
        $config = AppSetting::getAgentConfig($userId);

        $this->table(['Key', 'Value'], collect($config)->map(
            fn ($v, $k) => [$k, mb_strlen((string) $v) > 80 ? mb_substr((string) $v, 0, 77).'...' : $v]
        )->values()->all());

        return Command::SUCCESS;
    }
}
